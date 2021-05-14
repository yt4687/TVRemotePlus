<?php

	// カレントディレクトリを modules/ 以下に変更（こうしないと読み込んでくれない）
	chdir('../../modules/');

	// モジュール読み込み
	require_once ('classloader.php');
	require_once ('require.php');
	require_once ('module.php');

	// チャンネルを取得
	$cmd = new CtrlCmdUtil;
	if (isset($ctrlcmd_addr) && $ctrlcmd_addr !== '') {
		$cmd->setNWSetting($ctrlcmd_addr);
	}
	$ch = initBonChannel($cmd);

    // ストリーム番号を取得
	$stream = getStreamNumber($_SERVER['REQUEST_URI']);

	// 設定ファイル読み込み
	$ini = json_decode(file_get_contents_lock_sh($inifile), true);

	// 現在番組情報を取得する
	$epginfo = ['onair' => []];
	$service_ids = [];
	foreach ($ch as $chkey => $v) {

		$service_ids[] = 0;
		$service_ids[] = (($v['onid'] * 0x10000) + $v['tsid']) * 0x10000 + $v['sid'];
		$epginfo['onair'][$chkey] = [
			'ch' => $chkey,
			'ch_str' => strval($chkey),
			'tsid' => $v['tsid'],
			'channel' => 'チャンネル名を取得できませんでした',
			'duration' => '',
			'starttime' => '00:00',
			'to' => '～',
			'endtime' => '00:00',
			'program_name' => '番組情報を取得できませんでした',
			'program_info' => '番組情報を取得できませんでした',
			'next_starttime' => '00:00',
			'next_endtime' => '00:00',
			'next_program_name' => '番組情報を取得できませんでした',
			'next_program_info' => '番組情報を取得できませんでした',
		];
	}
	if (!empty($service_ids)) {

		// 各サービスの前後数時間分をとってくる
		$now = time();
		$service_ids[] = CtrlCmdUtil::unixTimeToJstFileTime($now - 9 * 3600);
		$service_ids[] = CtrlCmdUtil::unixTimeToJstFileTime($now + 6 * 3600);
		foreach ($cmd->sendEnumPgInfoEx($service_ids) ?? [] as $v) {

			// 開始時間が現在以下の番組のうち最大のもの
			$e_now = null;
			// 開始時間が現在より大きい番組のうち最小のもの
			$e_next = null;
			foreach ($v['eventList'] as $w) {
				if ($w['start_time'] <= $now) {
					if (!isset($e_now) || $e_now['start_time'] < $w['start_time']) $e_now = $w;
				} else {
					if (!isset($e_next) || $e_next['start_time'] > $w['start_time']) $e_next = $w;
				}
			}
			if (isset($e_now) && isset($e_now['durationSec']) && $e_now['start_time'] + $e_now['durationSec'] < $now) {
				// 終了している
				$e_now = null;
			}
			$chkey = ($v['serviceInfo']['onid'] < 15 ? $v['serviceInfo']['onid'] : 15) * 0x10000 + $v['serviceInfo']['sid'];
			$info = &$epginfo['onair'][$chkey];

			// チャンネル名
			$info['channel'] = mb_convert_kana($v['serviceInfo']['service_name'], 'asv');

			// 現在の番組
			if (isset($e_now)) {
				$duration = isset($e_now['durationSec']) ? $e_now['durationSec'] : 0;
				$info['timestamp'] = $e_now['start_time'];
				$info['duration'] = $duration;
				$info['starttime'] = date('Y/m/d H:i', $e_now['start_time']);
				$info['endtime'] = date('Y/m/d H:i', $e_now['start_time'] + $duration);
				$info['program_name'] = isset($e_now['shortInfo']) ?
					decorateMark(str_replace(["\r\n", "\n"], "<br>\n",
						htmlspecialchars(mb_convert_kana($e_now['shortInfo']['event_name'], 'asv', 'UTF-8')))) : '';
				$info['program_info'] = isset($e_now['shortInfo']) ?
					decorateMark(str_replace(["\r\n", "\n"], "<br>\n",
						htmlspecialchars(mb_convert_kana($e_now['shortInfo']['text_char'], 'asv', 'UTF-8')))) : '';
			} else {
				$info['program_name'] = '放送休止';
				$info['program_info'] = '放送休止';
			}

			// 次の番組
			if (isset($e_next)) {
				$duration = isset($e_next['durationSec']) ? $e_next['durationSec'] : 0;
				$info['next_starttime'] = date('Y/m/d H:i', $e_next['start_time']);
				$info['next_endtime'] = date('Y/m/d H:i', $e_next['start_time'] + $duration);
				$info['next_program_name'] = isset($e_next['shortInfo']) ?
					decorateMark(str_replace(["\r\n", "\n"], "<br>\n",
						htmlspecialchars(mb_convert_kana($e_next['shortInfo']['event_name'], 'asv', 'UTF-8')))) : '';
				$info['next_program_info'] = isset($e_next['shortInfo']) ?
					decorateMark(str_replace(["\r\n", "\n"], "<br>\n",
						htmlspecialchars(mb_convert_kana($e_next['shortInfo']['text_char'], 'asv', 'UTF-8')))) : '';
			} else {
				$info['next_program_name'] = '放送休止';
				$info['next_program_info'] = '放送休止';
			}
		}
	}

	foreach ($epginfo['onair'] as &$v) {
		global $nicologin_mail, $nicologin_password;
		
		// ------------- 実況勢い -------------

		// モデルを初期化
		$instance = new Jikkyo($nicologin_mail, $nicologin_password);
		
		// 実況 ID を取得
		$nicojikkyo_id = $instance->getNicoJikkyoID($v['channel']);
    
		// 実況 ID が存在する
		if ($nicojikkyo_id !== null) {

			// 実況勢いを取得
			$v['ikioi'] = $instance->getNicoJikkyoIkioi($nicojikkyo_id);

		} else {

			// 実況勢いを取得できなかった
			$v['ikioi'] = '-';
		}
	}

	$epginfo['api'] = 'epginfo';

	// ストリーム状態とストリームの番組情報を取得する
	foreach ($ini as $key => $value) {

		$key = strval($key);

		if ($ini[$key]['state'] == 'ONAir' or $ini[$key]['state'] == 'File'){
			$standby = file_get_contents($standby_m3u8);
			$stream_m3u8 = @file_get_contents($segment_folder.'stream'.$key.'.m3u8');
			if ($standby == $stream_m3u8){
				$status = 'standby';
			} else {
				$status = 'onair';
			}
		} else {
			$status = 'offline';
		}
	
		if ($ini[$key]['state'] === null) $ini[$key]['state'] = 'Offline';

		// ONAir状態なら
		if ($ini[$key]['state'] == 'ONAir'){

			// 番組情報を取得
			if (isset($epginfo['onair'][$ini[$key]['channel']])){
				$epginfo['stream'][$key] = $epginfo['onair'][$ini[$key]['channel']];
			// サブチャンネルをオフにした後にサブチャンネルのストリームが残っている場合用
			} else {
				$epginfo['stream'][$key] = array(
					'state' => $ini[$key]['state'],
					'status' => $status,
					'ch' => intval($ini[$key]['channel']),
					'ch_str' => strval($ini[$key]['channel']),
					'tsid' => '',
					'channel' => 'チャンネル名を取得できませんでした',
					'timestamp' => '', 
					'duration' => '', 
					'starttime' => '', 
					'to' => '', 
					'endtime' => '', 
					'program_name' => '番組情報を取得できませんでした',
					'program_info' => 'サブチャンネルの番組情報を表示するには、サブチャンネルが番組表に表示されている必要があります。<br>'.
					                  '右上の︙メニュー →［サブチャンネルを表示］から表示を切り替えられます。',
					'next_starttime' => '', 
					'next_endtime' => '', 
					'next_program_name' => '番組情報を取得できませんでした',
					'next_program_info' => '',
				);
			}

			// ステータス
			$epginfo['stream'][$key]['state'] = $ini[$key]['state'];
			$epginfo['stream'][$key]['status'] = $status;

			// チャンネル名が取得出来なかったら代入
			if (isset($ch[$ini[$key]['channel']]) and $epginfo['stream'][$key]['channel'] == 'チャンネル名を取得できませんでした'){
				$epginfo['stream'][$key]['channel'] = $ch[$ini[$key]['channel']];
			}

		// ファイル再生
		} else if ($ini[$key]['state'] == 'File'){

			$epginfo['stream'][$key] = array(
				'state' => $ini[$key]['state'],
				'status' => $status,
				'ch' => 0,
				'tsid' => 0,
				'channel' => $ini[$key]['filechannel'],
				'time' => $ini[$key]['filetime'],
				'start_timestamp' => $ini[$key]['start_timestamp'], 
				'end_timestamp' => $ini[$key]['end_timestamp'], 
				'program_name' => $ini[$key]['filetitle'],
				'program_info' => $ini[$key]['fileinfo'],
			);

		// オフライン
		} else {

			$epginfo['stream'][$key] = array(
				'state' => $ini[$key]['state'],
				'status' => $status,
				'ch' => 0,
				'ch_str' => '0',
				'tsid' => 0,
				'channel' => '',
				'timestamp' => '', 
				'duration' => '', 
				'starttime' => '', 
				'to' => '', 
				'endtime' => '', 
				'program_name' => '配信休止中…',
				'program_info' => '',
				'next_starttime' => '', 
				'next_endtime' => '', 
				'next_program_name' => '',
				'next_program_info' => '',
			);

		}
	}

	// 出力
	header('content-type: application/json; charset=utf-8');
	echo json_encode($epginfo, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
