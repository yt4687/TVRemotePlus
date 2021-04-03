<?php

	// モジュール読み込み
	require_once ('../../modules/require.php');
	require_once ('../../modules/module.php');

	// ストリーム番号を取得
	$stream = getStreamNumber($_SERVER['REQUEST_URI']);

	// 設定ファイル読み込み
	$ini = json_decode(file_get_contents_lock_sh($inifile), true);

	// セッションのファイル数を返す関数
	function getActiveCount() {
		$path = session_save_path();
		// 何もファイルなかったら0を返す
		if (empty($path)) {
			return 0;
		}
		// ファイル数検索
		$files = glob($path.'/sess_*');
		if ($files === false) {
			return 0;
		}
		// ファイル数を返す
		return count($files);
	}

	// セッションの有効期限
	ini_set('session.gc_maxlifetime', 10);
	ini_set('session.cookie_lifetime', 10);

	// セッションを確実に破棄する
	ini_set('session.gc_probability', 1);  // 分子(デフォルト:1)
	ini_set('session.gc_divisor', 1);  // 分母(デフォルト:100)

	// セッション保存ディレクトリ
	session_save_path($base_dir.'data/session/');

	// 視聴数カウント用セッション名
	// Twitter認証用にもセッションを使っていてIDが重複すると面倒な事になるので設定
	session_name('tvrp_watching_session');

	// セッション管理開始
	session_start();

	// 現在の視聴数を取得する
	$watching = getActiveCount();

	// ついでにストリーム状態を判定する
	if (isset($ini[$stream]) and ($ini[$stream]['state'] == 'ONAir' or $ini[$stream]['state'] == 'File')){

		if (!((!isset($ini[$stream]['fileext']) or ($ini[$stream]['fileext'] != 'mp4' or $ini[$stream]['fileext'] != 'mkv')) and
		       $ini[$stream]['state'] == 'File' and $ini[$stream]['encoder'] == 'Progressive')){

			// 比較元のm3u8
			if ($silent == 'true') $standby = file_get_contents($standby_silent_m3u8);
			else $standby = file_get_contents($standby_m3u8);
			// 比較先のm3u8
			$stream_m3u8 = file_get_contents($segment_folder.'stream'.$stream.'.m3u8');
			// 比較先のm3u8の更新日時
			$modified = filemtime($segment_folder.'stream'.$stream.'.m3u8');

			// m3u8が30秒経っても更新されない
			if ($standby == $stream_m3u8 and time() - $modified > 30){
				$status = 'failed';
			// 再生始まったけど更新が止まってしまった
			} else if ($ini[$stream]['state'] == 'ONAir' and time() - $modified > 20){
				$status = 'restart';
			// m3u8が更新されていない
			} else if ($standby == $stream_m3u8){
				$status = 'standby';
			// m3u8が更新されている
			} else {
				$status = 'onair';
			}

			$streamtype = 'normal';

		} else {

			$status = 'onair';
			$streamtype = 'progressive';

		}

	} else {
		$status = 'offline';
		$streamtype = 'normal';
	}
	
	if (!isset($ini[$stream]) or $ini[$stream]['state'] === null) $ini[$stream]['state'] = 'Offline';

	$json = array(
		'api' => 'status',
		'state' => $ini[$stream]['state'],
		'status' => $status,
		'watching' => $watching,
		'streamtype' => $streamtype,
	);

	// 出力
	header('content-type: application/json; charset=utf-8');
	echo json_encode($json, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
