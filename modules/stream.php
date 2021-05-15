<?php

	// 受信元アプリを識別する値。整数でほかのロケフリ系アプリと重複しないもの
	const SOURCE_APP_START = 300;
	// 起動できる受信元アプリの最大数
	const SOURCE_APP_MAX = 4;

	require_once (dirname(__FILE__).'/classloader.php');

	// コマンドラインからの場合
	if (isset($argc) and isset($argv)) {

		//ini_set('log_errors', 0);
		//ini_set('display_errors', 0);

		// モジュール読み込み
		require_once (dirname(__FILE__).'/require.php');
		require_once (dirname(__FILE__).'/module.php');

		// チャンネルを取得
		$cmd = new CtrlCmdUtil;
		if (isset($ctrlcmd_addr) && $ctrlcmd_addr !== '') {
			$cmd->setNWSetting($ctrlcmd_addr);
		}
		$ch = initBonChannel($cmd);

		// 設定読み込み
		$ini = json_decode(file_get_contents_lock_sh($inifile), true);

		// コマンドラインからのストリーム開始・停止はおまけ機能です
		// ファイル再生機能は今の所ついていません

		echo "\n";
		echo ' ---------------------------------------------------'."\n";
		echo '           TVRemotePlus-CommandLine '.$version."\n";
		echo ' ---------------------------------------------------'."\n";

		if ($argc < 3) {
			echo ' ---------------------------------------------------'."\n";
			echo '   Error: Argument is missing or too many.'."\n";
			echo '   Please Retry... m(__)m'."\n";
			echo ' ---------------------------------------------------'."\n";
			exit(1);
		}

		// ストリーム開始の引数：
		// stream.bat ONAir (ストリーム番号) (チャンネル番号)
		// stream.bat ONAir (ストリーム番号) (チャンネル番号) (動画の画質) (エンコーダー) (字幕データ (true ならオン・false ならオフ)) (使用 BonDriver)

		// ストリーム開始の場合
		if ($argv[1] == 'ONAir') {

			// ストリーム番号
			$stream = strval($argv[2]);

			// ステータス
			$ini[$stream]['state'] = 'ONAir';

			// チャンネル
			if (isset($argv[3])) {
				$channel = intval($argv[3]);
				if (isset($ch[$channel])) { // チャンネルが存在するかチェック
					$ini[$stream]['channel'] = $channel;
				} else {
					echo ' ---------------------------------------------------'."\n";
					echo '   Error: Channel '.$argv[3].' not found.'."\n";
					echo '   Please retry... m(__)m'."\n";
					echo ' ---------------------------------------------------'."\n";
					exit(1);
				}
			} else {
				echo ' ---------------------------------------------------'."\n";
				echo '   Error: Argument is missing.'."\n";
				echo '   Please retry... m(__)m'."\n";
				echo ' ---------------------------------------------------'."\n";
				exit(1);
			}

			// ↓ は指定されていなかったらデフォルト値を使う

			// 動画の画質
			if (isset($argv[4]) and $argv[4] != 'default') $ini[$stream]['quality'] = $argv[4];
			else $ini[$stream]['quality'] = getQualityDefault();

			// エンコーダー
			if (isset($argv[5]) and $argv[5] != 'default') $ini[$stream]['encoder'] = $argv[5];
			else $ini[$stream]['encoder'] = $encoder_default;

			// 字幕データ
			if (isset($argv[6]) and $argv[6] != 'default') $ini[$stream]['subtitle'] = $argv[6];
			else $ini[$stream]['subtitle'] = $subtitle_default;

			// ストリーム開始表示
			echo '   Starting stream...'."\n\n";
			echo '   Stream   : '.$stream."\n";
			echo '   Channel  : '.$ini[$stream]['channel']."\n";
			echo '   SID      : '.$ch[$ini[$stream]['channel']]['sid']."\n";
			echo '   TSID     : '.$ch[$ini[$stream]['channel']]['tsid']."\n";
			echo '   Quality  : '.$ini[$stream]['quality']."\n";
			echo '   Encoder  : '.$ini[$stream]['encoder']."\n";
			echo '   Subtitle : '.$ini[$stream]['subtitle']."\n";
			echo ' ---------------------------------------------------'."\n";
			echo "\n";

			// ストリームを終了する
			stream_stop($stream);

			// ストリームを開始する
			stream_start($stream, $ch[$ini[$stream]['channel']], $ini[$stream]['quality'], $ini[$stream]['encoder'], $ini[$stream]['subtitle']);

			// 準備中用の動画を流すためにm3u8をコピー
			if ($silent == 'true') {
				copy($standby_silent_m3u8, $base_dir.'htdocs/stream/stream'.$stream.'.m3u8');
			} else {
				copy($standby_m3u8, $base_dir.'htdocs/stream/stream'.$stream.'.m3u8');
			}

			// ファイル書き込み
			file_put_contents($inifile, json_encode($ini, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);

			echo ' ---------------------------------------------------'."\n";
			echo '   Stream started.'."\n";
			echo '   Processing completed.'."\n";
			echo ' ---------------------------------------------------'."\n";
			exit();


		// ストリーム終了の引数：
		// stream.bat Offline (ストリーム番号)

		// ストリーム終了の場合
		} else if ($argv[1] == 'Offline') {

			// ストリーム番号
			$stream = strval($argv[2]);

			// ステータス
			$ini[$stream]['state'] = 'Offline';

			// ストリーム終了表示
			echo '   Stopping stream...'."\n";
			echo ' ---------------------------------------------------'."\n";
			echo "\n";

			// ストリームを終了する
			stream_stop($stream);

			// Offline に設定する
			$ini[$stream]['state'] = 'Offline';
			$ini[$stream]['channel'] = '0';

			// 配信休止中用のプレイリスト (Stream 1のみ)
			if ($stream == '1') {
				if ($silent == 'true') {
					copy($offline_silent_m3u8, $base_dir.'htdocs/stream/stream'.$stream.'.m3u8');
				} else {
					copy($offline_m3u8, $base_dir.'htdocs/stream/stream'.$stream.'.m3u8');
				}
			// Stream 1 以外なら配列のキーごと削除する
			// m3u8 も削除
			} else {
				unset($ini[$stream]);
				@unlink($base_dir.'htdocs/stream/stream'.$stream.'.m3u8');
			}

			// ファイル書き込み
			file_put_contents($inifile, json_encode($ini, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);

			echo ' ---------------------------------------------------'."\n";
			echo '   Stream stoped.'."\n";
			echo '   Processing completed.'."\n";
			echo ' ---------------------------------------------------'."\n";
			exit();
		} else {

			echo ' ---------------------------------------------------'."\n";
			echo '   Error: Argument is missing or too many.'."\n";
			echo '   Please retry... m(__)m'."\n";
			echo ' ---------------------------------------------------'."\n";
			exit(1);
		}
	}

	// ライブ配信を開始する
	function stream_start($stream, $ch, $quality, $encoder, $subtitle) {

		global $inifile, $ffmpeg_path, $qsvencc_path, $nvencc_path, $vceencc_path, $segment_folder, $hlslive_time, $hlslive_list, $base_dir, $encoder_log, $encoder_window;

		// 設定ファイル読み込み
		$settings = json_decode(file_get_contents_lock_sh($inifile), true);

		// 以前の state が ONAir (受信元アプリを再利用できる)
		if ($settings[strval($stream)]['state'] === 'ONAir') {

			// 事前に前のストリームを終了する
			// 受信元アプリは再利用するため終了させない
			stream_stop($stream, false, true);

		// 以前の state が File か Offline
		} else {

			// 事前に前のストリームを終了する
			stream_stop($stream);

		}

		$cmd = new CtrlCmdUtil;

		$source_pid = 0;
		if (1 <= $stream && $stream <= SOURCE_APP_MAX) {
			$source_pid = $cmd->sendNwTVIDSetCh(['useSID' => 1, 'onid' => $ch['onid'], 'tsid' => $ch['tsid'], 'sid' => $ch['sid'], 'useBonCh' => 1, 'space' => SOURCE_APP_START + $stream, 'ch' => 2]) ?? 0;
		}
		$source_cmd = 'EDCB-NetworkTV-'.$source_pid;

		// 受信スキーム
		$receive = '\\\\.\\pipe\\SendTSTCP_0_'.$source_pid;

		if ($source_pid !== 0) {

			// 受信元アプリの準備が整うのを8秒まで待つ
			for ($elapsed_msec = 0; $elapsed_msec < 8000; $elapsed_msec += 200) {

				// glob() はパイプオープンを伴うので、ここでは使わない
				$dh = opendir('\\\\.\\pipe\\');
				if ($dh) {
					while (($file = readdir($dh)) !== false) {
						if (preg_match('/^SendTSTCP_[0-9]+_'.$source_pid.'$/i', $file)) {
							$receive = '\\\\.\\pipe\\'.$file;
							break;
						}
					}
					closedir($dh);
					if ($file !== false) {
						// 変換コマンドがパイプを閉じた直後かもしれない。再び開けるようになるまで少し待つ
						sleep(2);
						break;
					}
				}

				// opendir() がうまくいかない場合にそなえて終盤は file_exists() も使う
				if ($elapsed_msec > 5000) {
					for ($port = 9; $port >= 0; $port--) {
						$receive = '\\\\.\\pipe\\SendTSTCP_'.$port.'_'.$source_pid;
						if (file_exists($receive)) break;
					}
					if ($port >= 0) {
						// file_exists() はパイプオープンを伴う。再び開けるようになるまで少し待つ
						sleep(2);
						break;
					}
				}
				usleep(200000);
			}
		}

		// 字幕切り替え
		switch ($subtitle) {

			case 'true':
				$subtitle_ffmpeg_cmd = '-map 0 -ignore_unknown';
				$subtitle_other_cmd = '--sub-copy asdata';
			break;

			case 'false':
				$subtitle_ffmpeg_cmd = '';
				$subtitle_other_cmd = '';
			break;

			default:
				$subtitle_ffmpeg_cmd = '';
				$subtitle_other_cmd = '';
			break;
		}

		// 画質切り替え
		switch ($quality) {

			case '1080p-high':
				$width = 1920; // 動画の横幅
				$height = 1080; // 動画の高さ

				$vb = '6500k'; // 動画のビットレート
				$ab = '192k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '1080p':
				$width = 1440; // 動画の横幅
				$height = 1080; // 動画の高さ

				$vb = '6500k'; // 動画のビットレート
				$ab = '192k'; // 音声のビットレート
				$sar = '4:3'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '810p':
				$width = 1440; // 動画の横幅
				$height = 810; // 動画の高さ

				$vb = '5500k'; // 動画のビットレート
				$ab = '192k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '720p':
				$width = 1280; // 動画の横幅
				$height = 720; // 動画の高さ

				$vb = '4500k'; // 動画のビットレート
				$ab = '192k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '540p':
				$width = 960; // 動画の横幅
				$height = 540; // 動画の高さ

				$vb = '3000k'; // 動画のビットレート
				$ab = '192k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '360p':
				$width = 640; // 動画の横幅
				$height = 360; // 動画の高さ

				$vb = '1500k'; // 動画のビットレート
				$ab = '128k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '240p':
				$width = 426; // 動画の横幅
				$height = 240; // 動画の高さ

				$vb = '300k'; // 動画のビットレート
				$ab = '128k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '144p':
				$width = 256; // 動画の横幅
				$height = 144; // 動画の高さ

				$vb = '280k'; // 動画のビットレート
				$ab = '128k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;
		}

		// 変換コマンド切り替え
		switch ($encoder) {

			case($onid == 1 or $sid == 531):

				// ラジオ用コマンド。音声だけなのでffmpegだけでもたぶん大丈夫
				$stream_cmd = '"'.$ffmpeg_path.'"'.

					// ffmpeg用コマンド
					$stream_cmd = '"'.$ffmpeg_path.'"'.
	
						// 入力
						' -f mpegts -probesize 8192 -analyzeduration 0 -dual_mono_mode main -i "'.$receive.'"'.
						// HLS
						' -f hls'.
						' -hls_segment_type mpegts'.
						' -hls_time '.$hlslive_time.' -g '.($hlslive_time * 30).
						' -hls_list_size '.$hlslive_list.
						' -hls_allow_cache 0'.
						' -hls_flags delete_segments'.
						' -hls_segment_filename stream'.$stream.'-'.date('mdHi').'_%05d.ts'.
						// 音声
						' -acodec aac -ab '.$ab.' -ar '.$samplerate.' -ac 2 -af volume='.$volume.
						// 字幕
						' '.$subtitle_ffmpeg_cmd.
						// その他
						' -flags +loop+global_header -movflags +faststart -threads auto'.
						// 出力
						' stream'.$stream.'.m3u8';

				break;


				case 'ffmpeg':

					// ffmpeg用コマンド
					$stream_cmd = '"'.$ffmpeg_path.'"'.
	
						// 入力
						' -f mpegts -probesize 8192 -analyzeduration 0 -dual_mono_mode main -i "'.$receive.'"'.
						// HLS
						' -f hls'.
						' -hls_segment_type mpegts'.
						' -hls_time '.$hlslive_time.' -g '.($hlslive_time * 30).
						' -hls_list_size '.$hlslive_list.
						' -hls_allow_cache 0'.
						' -hls_flags delete_segments'.
						' -hls_segment_filename stream'.$stream.'-'.date('mdHi').'_%05d.ts'.
						// 映像
						' -vcodec libx264 -vb '.$vb.' -vf yadif=0:-1:1,scale='.$width.':'.$height.
						' -aspect 16:9 -preset veryfast -r 30000/1001'.
						// 音声
						' -acodec aac -ab '.$ab.' -ar '.$samplerate.' -ac 2 -af volume='.$volume.
						// 字幕
						' '.$subtitle_ffmpeg_cmd.
						// その他
						' -flags +loop+global_header -movflags +faststart -threads auto'.
						// 出力
						' stream'.$stream.'.m3u8';
	
					break;
	
				case 'QSVEncC':
	
					// QSVEncC用コマンド
					$stream_cmd = '"'.$qsvencc_path.'"'.
	
						// 入力
						' --input-format mpegts --input-analyze 0 -i "'.$receive.'"'.
						// avhw エンコード
						' --avhw'.
						// HLS
						' -m hls_time:'.$hlslive_time.' --gop-len '.($hlslive_time * 30).
						' -m hls_list_size:'.$hlslive_list.
						' -m hls_allow_cache:0'.
						' -m hls_flags:delete_segments'.
						' -m hls_segment_filename:stream'.$stream.'-'.date('mdHi').'_%05d.ts'.
						// 映像
						' --vbr '.$vb.' --qp-max 24:26:28 --output-res '.$width.'x'.$height.' --sar '.$sar.
						' --quality fastest --profile main --vpp-deinterlace normal --tff'.
						// 音声
						' --audio-codec aac#dual_mono_mode=main --audio-stream :stereo --audio-bitrate '.$ab.' --audio-samplerate '.$samplerate.
						' --audio-filter volume='.$volume.' --audio-ignore-decode-error 30 --audio-ignore-notrack-error'.
						// 字幕
						' '.$subtitle_other_cmd.
						// その他
						' --avsync forcecfr --fallback-rc --max-procfps 90 --output-thread 0'.
						// 出力
						' -o stream'.$stream.'.m3u8';
	
					break;
	
				case 'NVEncC':
	
					// NVEncC用コマンド
					$stream_cmd = '"'.$nvencc_path.'"'.
	
						// 入力
						' --input-format mpegts --input-analyze 0 -i "'.$receive.'"'.
						// avhw エンコード
						' --avhw'.
						// HLS
						' -m hls_time:'.$hlslive_time.' --gop-len '.($hlslive_time * 30).
						' -m hls_list_size:'.$hlslive_list.
						' -m hls_allow_cache:0'.
						' -m hls_flags:delete_segments'.
						' -m hls_segment_filename:stream'.$stream.'-'.date('mdHi').'_%05d.ts'.
						// 映像
						' --vbr '.$vb.' --qp-max 24:26:28 --output-res '.$width.'x'.$height.' --sar '.$sar.
						' --preset performance --profile main --cabac --vpp-deinterlace normal --tff'.
						// 音声
						' --audio-codec aac#dual_mono_mode=main --audio-stream :stereo --audio-bitrate '.$ab.' --audio-samplerate '.$samplerate.
						' --audio-filter volume='.$volume.' --audio-ignore-decode-error 30 --audio-ignore-notrack-error'.
						// 字幕
						' '.$subtitle_other_cmd.
						// その他
						' --avsync forcecfr --max-procfps 90 --output-thread 0'.
						// 出力
						' -o stream'.$stream.'.m3u8';
	
					break;
	
				case 'VCEEncC':
	
					// VCEEncC用コマンド
					$stream_cmd = '"'.$vceencc_path.'"'.
	
						// 入力
						' --input-format mpegts --input-analyze 0 -i "'.$receive.'"'.
						// avhw エンコード
						' --avhw'.
						// HLS
						' -m hls_time:'.$hlslive_time.' --gop-len '.($hlslive_time * 30).
						' -m hls_list_size:'.$hlslive_list.
						' -m hls_allow_cache:0'.
						' -m hls_flags:delete_segments'.
						' -m hls_segment_filename:stream'.$stream.'-'.date('mdHi').'_%05d.ts'.
						// 映像
						' --vbr '.$vb.' --qp-max 24:26:28 --output-res '.$width.'x'.$height.' --sar '.$sar.
						' --quality fast --profile main --interlace tff --vpp-afs preset=default'.
						// 音声
						' --audio-codec aac#dual_mono_mode=main --audio-stream :stereo --audio-bitrate '.$ab.' --audio-samplerate '.$samplerate.
						' --audio-filter volume='.$volume.' --audio-ignore-decode-error 30'.
						// 字幕
						' '.$subtitle_other_cmd.
						// その他
						' --avsync forcecfr --max-procfps 90'.
						// 出力
						' -o stream'.$stream.'.m3u8';
	
					break;
		}

		// ストリームを開始する（エンコーダーを起動する）
		$stream_cmd = 'start "'.$encoder.' Encoding..." '.($encoder_window == 'true' ? '' : '/B /min').' cmd.exe /C "'.win_exec_escape($stream_cmd);

		if ($encoder_log == 'true') {

			// 既にエンコーダーのログがあれば削除する
			if (file_exists("{$base_dir}logs/stream{$stream}.encoder.log")) {
				// PHP の unlink() 関数では削除に失敗する事があるため、del コマンドを使って削除する
				exec("del {$base_dir}logs/stream{$stream}.encoder.log");
			}

			$stream_cmd .= ' > '.$base_dir.'logs/stream'.$stream.'.encoder.log 2>&1';
		}

		// エンコーダー検索用コメントを含める
		$stream_cmd .= ' & rem tvrp:enc-'.$stream.'-"';

		win_exec('pushd "'.$segment_folder.'" && '.$stream_cmd);

		// エンコードコマンドと受信元アプリのコマンドを返す
		return array($stream_cmd, $source_cmd);

	}

	// ファイル再生を開始する
	function stream_file($stream, $filepath, $extension, $quality, $encoder, $subtitle) {

		global $ffmpeg_path, $qsvencc_path, $nvencc_path, $vceencc_path, $segment_folder, $hlsfile_time, $base_dir, $encoder_log, $encoder_window;

		// 事前に前のストリームを終了する
		stream_stop($stream);

		// dual_mono_mode
		if ($extension == 'mp4' or $extension == 'mkv') {
			$dual_mono_mode_ffmpeg = '';
			$dual_mono_mode_other = '';
		} else {
			$dual_mono_mode_ffmpeg = '-dual_mono_mode main';
			$dual_mono_mode_other = '#dual_mono_mode=main';
		}

		// 字幕切り替え
		switch ($subtitle) {

			case 'true':
				$subtitle_ffmpeg_cmd = '-map 0 -ignore_unknown';
				$subtitle_other_cmd = '--sub-copy asdata';
			break;

			case 'false':
				$subtitle_ffmpeg_cmd = '';
				$subtitle_other_cmd = '';
			break;


			default:
				$subtitle_ffmpeg_cmd = '';
				$subtitle_other_cmd = '';
			break;
		}

		// 画質切り替え
		switch ($quality) {
			case '1080p-high':
				$width = 1920; // 動画の横幅
				$height = 1080; // 動画の高さ

				$vb = '6800k'; // 動画のビットレート
				$ab = '192k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '1080p':
				$width = 1440; // 動画の横幅
				$height = 1080; // 動画の高さ

				$vb = '6800k'; // 動画のビットレート
				$ab = '192k'; // 音声のビットレート
				$sar = '4:3'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '810p':
				$width = 1440; // 動画の横幅
				$height = 810; // 動画の高さ

				$vb = '5800k'; // 動画のビットレート
				$ab = '192k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '720p':
				$width = 1280; // 動画の横幅
				$height = 720; // 動画の高さ

				$vb = '4800k'; // 動画のビットレート
				$ab = '192k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '540p':
				$width = 960; // 動画の横幅
				$height = 540; // 動画の高さ

				$vb = '3000k'; // 動画のビットレート
				$ab = '192k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '360p':
				$width = 640; // 動画の横幅
				$height = 360; // 動画の高さ

				$vb = '1500k'; // 動画のビットレート
				$ab = '128k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '240p':
				$width = 426; // 動画の横幅
				$height = 240; // 動画の高さ

				$vb = '300k'; // 動画のビットレート
				$ab = '128k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;

			case '144p':
				$width = 256; // 動画の横幅
				$height = 144; // 動画の高さ

				$vb = '280k'; // 動画のビットレート
				$ab = '128k'; // 音声のビットレート
				$sar = '1:1'; // アスペクト比(SAR)
				$samplerate = 48000; // 音声のサンプルレート
				$volume = 2.0; // 音量(元の音量の何倍か)
			break;
		}

		// 変換コマンド切り替え
		switch ($encoder) {

			case 'ffmpeg':

				// ffmpeg用コマンド
				$stream_cmd = '"'.$ffmpeg_path.'"'.

					// 入力
					' '.$dual_mono_mode_ffmpeg.' -i "'.$filepath.'"'.
					// HLS
					' -f hls'.
					' -hls_segment_type mpegts'.
					' -hls_time '.$hlsfile_time.' -g '.($hlsfile_time * 30).
					' -hls_list_size 0'.
					' -hls_allow_cache 0'.
					' -hls_flags delete_segments'.
					' -hls_segment_filename stream'.$stream.'-'.date('mdHi').'_%05d.ts'.
					// 映像
					' -vcodec libx264 -vb '.$vb.' -vf yadif=0:-1:1,scale='.$width.':'.$height.
					' -aspect 16:9 -preset veryfast -r 30000/1001'.
					// 音声
					' -acodec aac -ab '.$ab.' -ar '.$samplerate.' -ac 2 -af volume='.$volume.
					// 字幕
					' '.$subtitle_ffmpeg_cmd.
					// その他
					' -flags +loop+global_header -movflags +faststart -threads auto'.
					// 出力
					' stream'.$stream.'.m3u8';

				break;

			case 'QSVEncC':

				// QSVEncC用コマンド
				$stream_cmd = '"'.$qsvencc_path.'"'.

					// 入力
					' -i "'.$filepath.'"'.
					// avqsvエンコード
					' --avqsv'.
					// HLS
					' -m hls_time:'.$hlsfile_time.' --gop-len '.($hlsfile_time * 30).
					' -m hls_list_size:0'.
					' -m hls_allow_cache:0'.
					' -m hls_flags:delete_segments'.
					' -m hls_segment_filename:stream'.$stream.'-'.date('mdHi').'_%05d.ts'.
					// 映像
					' --vbr '.$vb.' --qp-max 24:26:28 --output-res '.$width.'x'.$height.' --sar '.$sar.
					' --quality balanced --profile Main --vpp-deinterlace normal --tff'.
					// 音声
					' --audio-codec aac'.$dual_mono_mode_other.' --audio-stream :stereo --audio-bitrate '.$ab.' --audio-samplerate '.$samplerate.
					' --audio-filter volume='.$volume.' --audio-ignore-decode-error 30 --audio-ignore-notrack-error'.
					// 字幕
					' '.$subtitle_other_cmd.
					// その他
					' --avsync forcecfr --fallback-rc --max-procfps 90 --output-thread 0'.
					// 出力
					' -o stream'.$stream.'.m3u8';

				break;

			case 'NVEncC':

				// NVEncC用コマンド
				$stream_cmd = '"'.$nvencc_path.'"'.

					// 入力
					' -i "'.$filepath.'"'.
					// avcuvidエンコード
					' --avcuvid'.
					// HLS
					' -m hls_time:'.$hlsfile_time.' --gop-len '.($hlsfile_time * 30).
					' -m hls_list_size:0'.
					' -m hls_allow_cache:0'.
					' -m hls_flags:delete_segments'.
					' -m hls_segment_filename:stream'.$stream.'-'.date('mdHi').'_%05d.ts'.
					// 映像
					' --vbr '.$vb.' --qp-max 24:26:28 --output-res '.$width.'x'.$height.' --sar '.$sar.
					' --preset default --profile Main --cabac --vpp-deinterlace normal --tff'.
					// 音声
					' --audio-codec aac'.$dual_mono_mode_other.' --audio-stream :stereo --audio-bitrate '.$ab.' --audio-samplerate '.$samplerate.
					' --audio-filter volume='.$volume.' --audio-ignore-decode-error 30 --audio-ignore-notrack-error'.
					// 字幕
					' '.$subtitle_other_cmd.
					// その他
					' --avsync forcecfr --max-procfps 90 --output-thread 0'.
					// 出力
					' -o stream'.$stream.'.m3u8';

				break;

			case 'VCEEncC':

				// VCEEncC用コマンド
				$stream_cmd = '"'.$vceencc_path.'"'.

					// 入力
					' -i "'.$filepath.'"'.
					// avhwエンコード
					' --avhw'.
					// HLS
					' -m hls_time:'.$hlsfile_time.' --gop-len '.($hlsfile_time * 30).
					' -m hls_list_size:0'.
					' -m hls_allow_cache:0'.
					' -m hls_flags:delete_segments'.
					' -m hls_segment_filename:stream'.$stream.'-'.date('mdHi').'_%05d.ts'.
					// 映像
					' --vbr '.$vb.' --qp-max 24:26:28 --output-res '.$width.'x'.$height.' --sar '.$sar.
					' --interlace tff --vpp-afs preset=default --profile Main'.
					// 音声
					' --audio-codec aac'.$dual_mono_mode_other.' --audio-stream :stereo --audio-bitrate '.$ab.' --audio-samplerate '.$samplerate.
					' --audio-filter volume='.$volume.' --audio-ignore-decode-error 30'.
					// 字幕
					' '.$subtitle_other_cmd.
					// その他
					' --avsync forcecfr --max-procfps 90'.
					// 出力
					' -o stream'.$stream.'.m3u8';

				break;
		}

		$stream_cmd = 'start "'.$encoder.' Encoding..." '.($encoder_window == 'true' ? '' : '/B /min').' cmd.exe /C "'.win_exec_escape($stream_cmd);

		// ログを書き出すかどうか
		if ($encoder_log == 'true') {

			// 既にエンコーダーのログがあれば削除する
			if (file_exists("{$base_dir}logs/stream{$stream}.encoder.log")) {
				// PHP の unlink() 関数では削除に失敗する事があるため、del コマンドを使って削除する
				exec("del {$base_dir}logs/stream{$stream}.encoder.log");
			}

			$stream_cmd .= ' > '.$base_dir.'logs/stream'.$stream.'.encoder.log 2>&1';
		}

		// エンコーダー検索用コメントを含める
		$stream_cmd .= ' & rem tvrp:enc-'.$stream.'-"';

		// ストリームを開始する
		win_exec('pushd "'.$segment_folder.'" && '.$stream_cmd);

		// エンコードコマンドを返す
		return $stream_cmd;
	}

	// ストリームを終了する
	// $allstop を true に設定すると全てのストリームを終了する
	// $exclude_source を true に設定すると終了対象から受信元アプリを除外する
	function stream_stop($stream, $allstop = false, $exclude_source = false) {

		global $ffmpeg_exe, $qsvencc_exe, $nvencc_exe, $vceencc_exe, $segment_folder;

		$cmd = new CtrlCmdUtil;

		// 全てのストリームを終了する
		if ($allstop) {

			// ffmpeg を終了する
			win_exec('taskkill /F /IM '.$ffmpeg_exe);

			// QSVEncC を終了する
			win_exec('taskkill /F /IM '.$qsvencc_exe);

			// NVEncC を終了する
			win_exec('taskkill /F /IM '.$nvencc_exe);

			// VCEEncC を終了する
			win_exec('taskkill /F /IM '.$vceencc_exe);

			// 受信元アプリを終了する
			if ($exclude_source === false) {
				for ($i = 1; $i <= SOURCE_APP_MAX; $i++) {
					$cmd->sendNwTVIDClose(SOURCE_APP_START + $i);
				}
			}

			// フォルダ内の TS を削除
			win_exec('pushd "'.$segment_folder.'" && del *.ts /S');

		// このストリームを終了する
		} else {

			// エンコーダー検索用コメントを使ってエンコーダーを終了させる
			$parent_pids = array();
			exec('wmic process where "commandline like \'% [r]em tvrp:enc-'.$stream.'-%\'" get processid 2>nul | findstr /b [1-9]', $parent_pids);
			foreach ($parent_pids as $parent_pid) {
				$encoder_pid = (int)exec('wmic process where "parentprocessid = '.(int)$parent_pid.'" get processid 2>nul | findstr /b [1-9]');
				if ($encoder_pid > 0) {
					win_exec('taskkill /F /PID '.$encoder_pid);
				}
			}

			if ($exclude_source === false) {
				// 受信元アプリを終了する
				if (1 <= $stream and $stream <= SOURCE_APP_MAX) {
					$source_pid = $cmd->sendNwTVIDSetCh(['useSID' => 0, 'onid' => 0, 'tsid' => 0, 'sid' => 0, 'useBonCh' => 1, 'space' => SOURCE_APP_START + $stream, 'ch' => 0]) ?? 0;
					if ($source_pid !== 0) {
						$cmd->sendNwTVIDClose(SOURCE_APP_START + $stream);
					}
				}
			}

			// フォルダ内のTSを削除
			win_exec('pushd "'.$segment_folder.'" && del stream'.$stream.'*.ts /S');
		}
	}
