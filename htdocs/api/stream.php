<?php

	// モジュール読み込み
	require_once ('../../modules/require.php');
	require_once ('../../modules/module.php');

    // ストリーム番号を取得
	$stream = getStreamNumber($_SERVER['REQUEST_URI']);

	// 設定ファイル読み込み
	$ini = json_decode(file_get_contents($inifile), true);

	// 動画を出力する関数
	// https://blog.logicky.com/2019/05/29/151209?utm_source=feed
	// を大変参考にさせていただきました、ありがとうございます
	function loadVideo($file, $extension){

		$fp = @fopen($file, 'rb'); // ファイルを開く
		$size   = filesize($file); // ファイルサイズ
		$length = $size;           // Content length
		$start  = 0;               // 開始バイト
		$end    = $size - 1;       // 終了バイト

		if ($extension == 'mp4'){
			header('Content-type: video/mp4');
		} else if ($extension == 'mkv'){
			header('Content-type: video/x-matroska');
		}
		header("Accept-Ranges: 0-$length");

		// ブラウザがHTTP_RANGEを要求してきた場合
		if (isset($_SERVER['HTTP_RANGE'])) {

			$c_start = $start;
			$c_end   = $end;

			// レンジ範囲を取得
			list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

			// 不正なリクエスト
			if (strpos($range, ',') !== false){
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}

			// レンジ範囲を解析
			if ($range == '-'){
				$c_start = $size - substr($range, 1);
			} else {
				$range  = explode('-', $range);
				$c_start = $range[0];
				$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}

			$c_end = ($c_end > $end) ? $end : $c_end;

			// 不正なレンジ範囲
			if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size){
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}

			// 取得し直したバイト位置・長さを代入
			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1;

			// ポインタを開始位置まで移動
			fseek($fp, $start);

			// HTTP_RANGEに対応していることを伝える
			header('HTTP/1.1 206 Partial Content');
		}

		// コンテンツ範囲を伝える
		header("Content-Range: bytes $start-$end/$size");
		header('Content-Length: '.$length);

		// 8192B(8KB)ごとに出力
		$buffer = 1024 * 8;

		// ファイルの終端か終了位置に到達するまで繰り返す
		while (!feof($fp) && ($p = ftell($fp)) <= $end){

			// バッファサイズ分残りのデータがない場合バッファ範囲を修正
			if ($p + $buffer > $end) {
				$buffer = $end - $p + 1;
			}

			// ファイルを読み込んで出力する
			set_time_limit(0);
			echo fread($fp, $buffer);
			flush();
		}

		// ファイルを閉じる
		fclose($fp);
	}

	if (isset($_GET['file'])){
		$file = rawurldecode($_GET['file']);
		$pathinfo = pathinfo($TSfile_dir.'/'.$file);
	}

	// 指定されたファイル
	// ファイルが指定されていてかつファイルが存在していてかつ拡張子が存在していてかつ拡張子がMP4かMKVなら
	if (isset($file) and file_exists($TSfile_dir.'/'.$file) and isset($pathinfo['extension']) and
	    ($pathinfo['extension'] == 'mp4' or $pathinfo['extension'] == 'mkv')){

		loadVideo($TSfile_dir.'/'.$_GET['file'], $pathinfo['extension']);
		exit();	
		
	// 指定されたストリームのファイルを出力する(stateがFileでかつMP4・MKVのみ)
	} else if ($ini[$stream]['state'] == 'File' and $ini[$stream]['encoder'] == 'Progressive' and
	           ($ini[$stream]['fileext'] == 'mp4' or $ini[$stream]['fileext'] == 'mkv')){

		loadVideo($TSfile_dir.'/'.$ini[$stream]['filepath'], $ini[$stream]['fileext']);
		exit();

	} else {

		// エラー画像
		header('Content-Type: image/jpeg');
		readfile('../files/thumb_default.jpg');
		
		exit();

	}
