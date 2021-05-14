<?php

	// 局ロゴ情報 (LogoData.ini) から局ロゴ取得

	// モジュール読み込み
	require_once ('../../modules/require.php');

	$logo_dir = rtrim(str_replace('\\', '/', $logo_dir ?? ''), '/');
	$logo_extension = null;

	// クエリ
	$logo_onid = isset($_REQUEST['onid']) ? filter_var($_REQUEST['onid'], FILTER_VALIDATE_INT) : false;
	$logo_sid = isset($_REQUEST['sid']) ? filter_var($_REQUEST['sid'], FILTER_VALIDATE_INT) : false;

	// onid と sid がついている場合のみ
	if ($logo_onid !== false and $logo_sid !== false and $logo_dir !== '') {
		
		// ロゴ識別と ServiceID との対応を調べる
		$logo_ini = @file_get_contents($logo_dir.'/LogoData.ini');
		if ($logo_ini !== false and
		    preg_match(sprintf('/^%04X%04X=([0-9]+)/im', $logo_onid, $logo_sid), $logo_ini, $matches)) {

			$pattern = sprintf('%04X_%03X_*', $logo_onid, $matches[1]);
			// 大小文字マッチにする
			$pattern = str_replace(['A', 'B', 'C', 'D', 'E', 'F'],
			                       ['[Aa]', '[Bb]', '[Cc]', '[Dd]', '[Ee]', '[Ff]'], $pattern);

			$search = glob($logo_dir.'/LogoData/'.$pattern);
			if (empty($search)) {
				$search = glob($logo_dir.'/Logo/'.$pattern);
			}
			if (!empty($search)) {
				// ファイル名の末尾2桁はロゴタイプ (STD-B21)。形式やサイズの違うものから良いものを選ぶ
				foreach ([['05', 'png'], ['05', 'bmp'],
				          ['02', 'png'], ['02', 'bmp'],
				          ['04', 'png'], ['04', 'bmp'],
				          ['01', 'png'], ['01', 'bmp'],
				          ['03', 'png'], ['03', 'bmp'],
				          ['00', 'png'], ['00', 'bmp']] as $pair) {
					foreach ($search as $logo_path) {
						if (preg_match('/'.$pair[0].'\\.'.$pair[1].'$/i', $logo_path)) {
							$logo_extension = $pair[1];
							break;
						}
					}
					if (isset($logo_extension)) {
						break;
					}
				}
			}
		}
	}

	if (isset($logo_extension)) {

		// ブラウザにキャッシュしてもらえるようにヘッダーを設定
		// 参考: https://qiita.com/yuuuking/items/4f11ccfc822f4c198ab0
		header('Cache-Control: public, max-age=2592000');  // 30日間
		header('Content-Type: image/'.$logo_extension);

		// 局ロゴを出力
		readfile($logo_path);

		exit();

	} else {

		// エラー画像
		header('Content-Type: image/jpeg');
		readfile('../files/thumb_default.jpg');
		
		exit();
	}
