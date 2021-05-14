<?php

	// モジュール読み込み
	require_once ('../../modules/require.php');
	require_once ('../../modules/module.php');
	require_once ('../../modules/classloader.php');

	// かなり長くなることがあるので実行時間制限をオフに
	ignore_user_abort(true);
	set_time_limit(0);

	// jsonからデコードして代入
	$TSfile = file_get_contents_lock_sh($infofile);
	if ($TSfile !== false) {
		$TSfile = json_decode($TSfile, true);
	} else {
		$TSfile = array('data' => array());
	}

	// リストリセット
	if (isset($_GET['list_reset'])){

		// jsonを空にする
		file_put_contents($infofile, json_encode(array('data' => array()), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
		// ロックファイルを削除
		@unlink($infofile.'.lock');

		$json = array(
			'api' => 'listupdate',
			'status' => 'list_reset',
		);

		// レスポンス
		echo json_encode($json, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

		exit;
	}

	// 再生履歴を削除
	if (isset($_GET['history_reset'])){

		// jsonを空にする
		file_put_contents($historyfile, json_encode(array('data' => array()), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);

		$json = array(
			'api' => 'listupdate',
			'status' => 'history_reset',
		);

		// レスポンス
		echo json_encode($json, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

		exit;
	}

	// 録画終了時刻を計算する関数
	function calc_end_time($start, $duration){

		// それぞれ切り出す
		$start_hour = mb_substr($start, 0, 2);
		$start_min = mb_substr($start, 3, 2);

		// 十の位が0なら消す
		if (mb_substr($start_hour, 0, 1) == 0) $start_hour = mb_substr($start_hour, 1, 1);
		if (mb_substr($start_min, 0, 1) == 0) $start_min = mb_substr($start_min, 1, 1);

		// 数値に変換
		$start_hour = intval($start_hour);
		$start_min = intval($start_min);
	
		if ($start_hour !== 0){
			// 繰り上がり
			$start = ($start_hour * 60) + $start_min;
		} else {
			// 繰り上がらない
			$start = $start_min;
		}

		// 開始時間と長さを足して終了時間(分)を出す
		$end_ = $start + $duration;
		// 割って繰り上がりさせる
		$end_hour = floor($end_ / 60);
		$end_min = floor($end_ % 60);

		// 0埋めする
		$end = sprintf('%02d', $end_hour).':'.sprintf('%02d', $end_min);
	
		return $end;
	}

	// 録画ファイルの長さ(分)を計算する関数
	function calc_duration_time($duration){

		// それぞれ切り出す
		$duration_hour = mb_substr($duration, 0, 2);
		$duration_min = mb_substr($duration, 3, 2);

		// 十の位が0なら消す
		if (mb_substr($duration_hour, 0, 1) == 0) $duration_hour = mb_substr($duration_hour, 1, 1);
		if (mb_substr($duration_min, 0, 1) == 0) $duration_min = mb_substr($duration_min, 1, 1);

		// 数値に変換
		$duration_hour = intval($duration_hour);
		$duration_min = intval($duration_min);

		if ($duration_hour !== 0){
			// 繰り上がり
			$duration = ($duration_hour * 60) + $duration_min;
		} else {
			// 繰り上がらない
			$duration = $duration_min;
		}

		return $duration;
	}

	// $TSfile['data'] にはファイル自体の情報を配列として格納、
	// $TSfile['info'] にはファイルの情報をキー名を拡張子なしファイル名とした連想配列に格納
	// $TSfile['data'] は今の所リスト更新毎に $TSfile['info'] からデータを持ってきて作り直している
	// このあたりかなりコードぐっちゃぐちゃなのでそのうち作り直したい

	// lockファイルがない or 手動更新なら
	if (!file_exists($infofile.'.lock') or isset($_GET['manual'])){

		// lockファイルを作成
		file_put_contents($infofile.'.lock', '');

		// ファイルを四階層まで検索する
		// MP4・MKVファイルも検索する
		$search_extensions = array('ts', 'mts', 'm2t', 'm2ts', 'mp4', 'mkv');
		$search = @scandir_and_match_files($TSfile_dir, '/.\\.('.implode('|', $search_extensions).')$/i', 4);
		if ($search === false) {
			$search = array();
		}

		// 拡張子順にソート
		usort($search, function ($a, $b) use ($search_extensions) {
			$cmp = array_search(strtolower(substr($a, strrpos($a, '.') + 1)), $search_extensions, true) -
			       array_search(strtolower(substr($b, strrpos($b, '.') + 1)), $search_extensions, true);
			return $cmp !== 0 ? $cmp : strcmp($a, $b);
		});

		$TSfile['data'] = array();

		$rec_info_dict = null;

		foreach ($search as $key => $value) {
			
			// 誤作動の原因になるので変数は破棄しておく
			unset($ffmpeg_cmd, $ffmpeg_result, $ffmpeg_return,
				  $rplsinfo_cmd, $rplsinfo_result, $rplsinfo_return, 
				  $ffprobe_cmd, $ffprobe_result, $ffprobe_return);

			// 録画ファイル保存フォルダからのパスを含めたファイル名
			$TSfile['data'][$key]['file'] = '/'.$value;
			// Pathinfo
			$TSfile['data'][$key]['pathinfo'] = pathinfo($TSfile_dir.'/'.$value);
			// 拡張子なしファイル名を暫定でタイトルにしておく
			$TSfile['data'][$key]['title'] = decorateMark(str_replace('　', ' ', $TSfile['data'][$key]['pathinfo']['filename']));
			// タイトル(HTML抜き)
			$TSfile['data'][$key]['title_raw'] = str_replace('　', ' ', $TSfile['data'][$key]['pathinfo']['filename']);
			// ファイルの更新日時(Unix時間)
			$TSfile['data'][$key]['update'] = filemtime($TSfile_dir.'/'.$value);

			// dirnameは削除しておく(セキュリティ上の問題)
			$pathinfo_dirname = $TSfile['data'][$key]['pathinfo']['dirname'];
			unset($TSfile['data'][$key]['pathinfo']['dirname']);

			// ファイル名のmd5
			$md5 = md5($TSfile_dir.'/'.$value);

			// サムネイルが存在するなら
			if (file_exists($base_dir.'htdocs/files/thumb/'.$md5.'.jpg')){

				$TSfile['data'][$key]['thumb_state'] = 'generated'; // サムネイル生成フラグ
				$TSfile['data'][$key]['thumb'] = $md5.'.jpg'; // サムネイル画像のパス(拡張子なしファイル名のmd5)

			// 以前サムネイル生成に失敗している場合
			// サムネイル生成に失敗した＝壊れてるTSファイルなので、毎回生成させると時間を食う
			} else if (isset($TSfile['info'][$TSfile['data'][$key]['pathinfo']['filename']]['thumb_state']) and 
					$TSfile['info'][$TSfile['data'][$key]['pathinfo']['filename']]['thumb_state'] == 'failed'){

				$TSfile['data'][$key]['thumb_state'] = 'failed'; // サムネイル生成フラグ
				$TSfile['data'][$key]['thumb'] = 'thumb_default.jpg'; // サムネイル画像のパス

			} else { 

				// ないならデフォルトにする
				$TSfile['data'][$key]['thumb'] = 'thumb_default.jpg'; // サムネイル画像のパス

				// ffmpegでサムネイルを生成
				$ffmpeg_cmd = '"'.$ffmpeg_path.'" -y -ss 72 -i "'.$TSfile_dir.'/'.$value.'" -vframes 1 -f image2 -s 480x270 "'.$base_dir.'htdocs/files/thumb/'.$md5.'.jpg" 2>&1';
				exec($ffmpeg_cmd, $ffmpeg_result, $ffmpeg_return);

				// 生成成功
				if ($ffmpeg_return === 0){

					// サムネイル生成フラグ
					$TSfile['data'][$key]['thumb_state'] = 'generated';

				// 生成失敗
				} else {

					// サムネイル生成フラグ
					$TSfile['data'][$key]['thumb_state'] = 'failed';

				}

				// サムネイル画像のパス(拡張子なしファイル名のmd5)
				// 仮になかった場合デフォルト画像が表示される
				$TSfile['data'][$key]['thumb'] = $md5.'.jpg';
			}

			// デフォルト値（取得に失敗したとき用）
			$TSfile['data'][$key]['date'] = date('Y/m/d', $TSfile['data'][$key]['update']); // 更新日時から推測
			$TSfile['data'][$key]['info_state'] = 'failed'; // 番組情報取得フラグ
			$TSfile['data'][$key]['info'] = '取得できませんでした';
			$TSfile['data'][$key]['channel'] = '取得失敗';
			$TSfile['data'][$key]['start'] = date('H:i', $TSfile['data'][$key]['update'] - (30 * 60));
			$TSfile['data'][$key]['end'] = date('H:i', $TSfile['data'][$key]['update']);
			$TSfile['data'][$key]['duration'] = '30?';
			$TSfile['data'][$key]['start_timestamp'] = $TSfile['data'][$key]['update'] - (30 * 60); // 分からないので取りあえず30分引いとく
			$TSfile['data'][$key]['end_timestamp'] = $TSfile['data'][$key]['update'];

			// 番組情報が取得できているなら
			if (isset($TSfile['info'][$TSfile['data'][$key]['pathinfo']['filename']]['info_state']) and 
				$TSfile['info'][$TSfile['data'][$key]['pathinfo']['filename']]['info_state'] == 'generated'){

				// 拡張子の情報を一時的に保管
				$extension = $TSfile['data'][$key]['pathinfo']['extension'];
				
				// 前に取得した情報を読み込む
				// MP4・MKVからは番組情報を取得できないので、同じファイル名のTSがあればその番組情報を使う
				$TSfile['data'][$key] = $TSfile['info'][$TSfile['data'][$key]['pathinfo']['filename']];

				// ファイルパスがTSのものに上書きされてしまうのでここで戻しておく
				$TSfile['data'][$key]['file'] = '/'.$value;

				// 拡張子も.tsとして上書きされてしまうのでこれも戻しておく（ついでに小文字化）
				$TSfile['data'][$key]['pathinfo']['extension'] = strtolower($extension);
				
				// 番組情報取得フラグ
				$TSfile['data'][$key]['info_state'] = 'generated';

			// 番組情報を取得していないなら
			// MP4・MKVには番組情報は含まれていないので除外
			} else if ($TSfile['data'][$key]['pathinfo']['extension'] != 'mp4' and $TSfile['data'][$key]['pathinfo']['extension'] != 'mkv'){

				// rplsinfoでファイル情報を取得
				$rplsinfo_cmd = '"'.$rplsinfo_path.'" -C -dtpcbieg -l 10 "'.$TSfile_dir.'/'.$value.'" 2>&1';
				exec($rplsinfo_cmd, $rplsinfo_result, $rplsinfo_return);

				// 取得成功
				if ($rplsinfo_return == 0){

					$rplsinfo_result = mb_convert_encoding(implode("\n", $rplsinfo_result), 'UTF-8', 'SJIS'); // 実行結果の配列を連結して一旦文字列に
					// 正規表現でエラーメッセージを置換する
					$rplsinfo_result = preg_replace("/番組情報元ファイル.*?は有効なTS, rplsファイルではありません./", '', $rplsinfo_result);
					$rplsinfo_result = preg_replace("/番組情報元ファイル.*?から有効な番組情報を検出できませんでした./", '', $rplsinfo_result);
					$rplsinfo_result = preg_replace("/番組情報元ファイル.*?を開くのに失敗しました./", '', $rplsinfo_result);
				
					$fileinfo = str_getcsv(str_replace('　', ' ', mb_convert_kana($rplsinfo_result, 'asv', 'UTF-8'))); // Parseして配列にする

					// 出力
					$TSfile['data'][$key]['title'] = decorateMark($fileinfo[4]); // 取得した番組名の方が正確なので修正
					$TSfile['data'][$key]['title_raw'] = $fileinfo[4]; // 取得した番組名の方が正確なので修正
					$TSfile['data'][$key]['date'] = $fileinfo[0]; // 録画日付
					$TSfile['data'][$key]['info_state'] = 'generated'; // 番組情報取得フラグ
					$TSfile['data'][$key]['info'] = $fileinfo[5]; // 番組情報
					$TSfile['data'][$key]['channel'] = $fileinfo[3]; //チャンネル名
					$TSfile['data'][$key]['start'] = substr($fileinfo[1], 0, strlen($fileinfo[1])-3); // 番組の開始時刻
					$TSfile['data'][$key]['end'] = calc_end_time($fileinfo[1], calc_duration_time($fileinfo[2])); // 番組の終了時刻
					$TSfile['data'][$key]['duration'] = calc_duration_time($fileinfo[2]); // ファイルの時間を算出
					$TSfile['data'][$key]['start_timestamp'] = strtotime($fileinfo[0].' '.$TSfile['data'][$key]['start']); // 開始時刻のタイムスタンプ
					$TSfile['data'][$key]['end_timestamp'] = strtotime($fileinfo[0].' '.$TSfile['data'][$key]['start']) + (calc_duration_time($fileinfo[2]) * 60); // 終了時刻のタイムスタンプ

					// start_timestamp よりも end_timestamp の方が小さい場合は日付を跨いだと計算し1日足す
					if ($TSfile['data'][$key]['start_timestamp'] > $TSfile['data'][$key]['end_timestamp']){
						$TSfile['data'][$key]['end_timestamp'] += 86400;
					}

					unset($TSfile['data'][$key]['tsinfo_state']);

					// 結果を保存する
					$TSfile['info'][$TSfile['data'][$key]['pathinfo']['filename']] = $TSfile['data'][$key];
				}
			}

			// この時点で番組情報を取得できていない場合、.ts.program.txt からの取得を試みる
			if ($TSfile['data'][$key]['duration'] === '30?' and !isset($TSfile['data'][$key]['tsinfo_state'])){

				if (!isset($rec_info_dict)) {
					$rec_info_dict = array();
					// 録画済み一覧を取得
					$cmd = new CtrlCmdUtil;
					if (isset($ctrlcmd_addr) && $ctrlcmd_addr !== '') {
						$cmd->setNWSetting($ctrlcmd_addr);
					}
					// 拡張子つき/なしのファイル名をキーとする辞書化
					foreach ($cmd->sendEnumRecInfoBasic2() ?? array() as $info) {
						if (!empty($info['recFilePath'])) {
							$rec_info_dict[strtoupper(pathinfo($info['recFilePath'], PATHINFO_BASENAME))] = $info;
							$rec_info_dict[strtoupper(pathinfo($info['recFilePath'], PATHINFO_FILENAME))] = $info;
						}
					}
				}

				// 拡張子を除いたファイル名が録画済み一覧と一致するものを探す
				$info = $rec_info_dict[strtoupper($TSfile['data'][$key]['pathinfo']['filename'])] ?? null;
				if (isset($info)) {
					$TSfile['data'][$key]['title'] = decorateMark(htmlspecialchars(mb_convert_kana($info['title'], 'asv', 'UTF-8'))); // 取得した番組名の方が正確なので修正
					$TSfile['data'][$key]['title_raw'] = mb_convert_kana($info['title'], 'asv', 'UTF-8'); // 取得した番組名の方が正確なので修正
					$TSfile['data'][$key]['date'] = date('Y/m/d', $info['startTime']); // 録画日付
					$TSfile['data'][$key]['info_state'] = 'generated'; // 番組情報取得フラグ

					// 詳細情報も含めて取得する
					$exinfo = $cmd->sendGetRecInfo2($info['id']);
					if (isset($exinfo) && $exinfo['programInfo'] !== '') {
						// .program.txt が見つかった
						$TSfile['data'][$key]['info'] = htmlspecialchars(programToArray($exinfo['programInfo'])['info'] ?? ''); // 番組情報
					} elseif ($info['eid'] !== 0xFFFF) {
						// プログラム予約でなければ過去番組情報を探してみる
						$exinfo = $cmd->sendEnumPgArc(array(0, (($info['onid'] * 0x10000) + $info['tsid']) * 0x10000 + $info['sid'],
						                                    CtrlCmdUtil::unixTimeToJstFileTime($info['startTime'] - 1),
						                                    CtrlCmdUtil::unixTimeToJstFileTime($info['startTime'] + 1)));
						if (!empty($exinfo) && !empty($exinfo[0]['eventList'])) {
							// 過去番組情報が見つかった
							$exinfo = $exinfo[0]['eventList'][0];
						} else {
							// 番組情報を探してみる
							$exinfo = $cmd->sendGetPgInfo($info['onid'], $info['tsid'], $info['sid'], $info['eid']);
						}
						if (isset($exinfo['shortInfo'])) {
							$TSfile['data'][$key]['info'] = htmlspecialchars(mb_convert_kana($exinfo['shortInfo']['text_char'], 'asv', 'UTF-8')); // 番組情報
						}
					}
					$TSfile['data'][$key]['channel'] = mb_convert_kana($info['serviceName'], 'asv', 'UTF-8'); //チャンネル名
					$TSfile['data'][$key]['start'] = date('H:i', $info['startTime']); // 番組の開始時刻
					$TSfile['data'][$key]['end'] = date('H:i', $info['startTime'] + $info['durationSecond']); // 番組の終了時刻
					$TSfile['data'][$key]['duration'] = intval(round($info['durationSecond'] / 60)); // ファイルの時間を算出
					$TSfile['data'][$key]['start_timestamp'] = $info['startTime']; // 開始時刻のタイムスタンプ
					$TSfile['data'][$key]['end_timestamp'] = $info['startTime'] + $info['durationSecond']; // 終了時刻のタイムスタンプ

					unset($TSfile['data'][$key]['tsinfo_state']);

					// 結果を保存する
					$TSfile['info'][$TSfile['data'][$key]['pathinfo']['filename']] = $TSfile['data'][$key];
				
				// ffprobe で動画の長さだけでも取得する
				} else {
				
					// コマンドを実行
					$ffprobe_cmd = '"'.$ffprobe_path.'" -i "'.$TSfile_dir.'/'.$value.'" -loglevel quiet -show_streams -print_format json';
					exec($ffprobe_cmd, $ffprobe_result, $ffprobe_return);

					if ($ffprobe_return === 0){

						$TSinfo = json_decode(implode("\n", $ffprobe_result), true);

						$TSfile['data'][$key]['tsinfo_state'] = 'generated';

						// 取得した情報を格納
						if (isset($TSinfo['streams'][0]['duration'])){
							$duration = round($TSinfo['streams'][0]['duration']); // 小数点以下四捨五入
						} else {
							$duration = 0; // 取得できなかった場合
						}
						$TSfile['data'][$key]['start_timestamp'] = $TSfile['data'][$key]['update'] - $duration;
						$TSfile['data'][$key]['end_timestamp'] = $TSfile['data'][$key]['update'];
						$TSfile['data'][$key]['start'] = date('H:i', $TSfile['data'][$key]['start_timestamp']).'?';
						$TSfile['data'][$key]['end'] = date('H:i', $TSfile['data'][$key]['end_timestamp']).'?';
						$TSfile['data'][$key]['duration'] = intval(round($duration / 60));

					}
				}
			}

			// 無駄な空白や改行を削除
			if (isset($TSfile['data'][$key]['title'])) $TSfile['data'][$key]['title'] = trim($TSfile['data'][$key]['title']);
			if (isset($TSfile['data'][$key]['title_raw'])) $TSfile['data'][$key]['title_raw'] = trim($TSfile['data'][$key]['title_raw']);
			if (isset($TSfile['data'][$key]['info'])) $TSfile['data'][$key]['info'] = trim($TSfile['data'][$key]['info']);

		}

		// もう存在しないファイルの情報を削除
		if (isset($TSfile['info'])) {
			$swept_info = array();
			foreach ($TSfile['data'] as $value) {
				if (isset($TSfile['info'][$value['pathinfo']['filename']])) {
					$swept_info[$value['pathinfo']['filename']] = $TSfile['info'][$value['pathinfo']['filename']];
				}
			}
			$TSfile['info'] = $swept_info;
		}

		// ファイルに保存
		file_put_contents($infofile, json_encode($TSfile, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);

		// lockファイルを削除
		@unlink($infofile.'.lock');

		$json = array(
			'api' => 'listupdate',
			'status' => 'success',
		);

	} else {

		$json = array(
			'api' => 'listupdate',
			'status' => 'duplication',
		);

	}

	// 出力
	header('content-type: application/json; charset=utf-8');
	echo json_encode($json, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
