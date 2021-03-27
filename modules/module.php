<?php
	
	// ***** 各種モジュール関数 *****

	// 参考: http://www.cattlemute.com/2019/09/14/1830/
	function is_empty($var = null) {
		// 論理型の false を取り扱う場合は、更に「&& false !== $var」を追加する
		if (empty( $var) && 0 !== $var && '0' !== $var ) {
			return true;
		} else {
			return false;
		}
	}

	// Windows用コマンド実行関数
	// proc_open を用いることで非同期でも実行できるようにする
	function win_exec($cmd, $log = null, $errorlog = null){

		$descriptorspec[0] = array('pipe', 'r');
		if ($log !== null){
			$descriptorspec[1] = array('file', $log);
		} else {
			$descriptorspec[1] = array('pipe', 'w');
		}
		if ($errorlog !== null){
			$descriptorspec[2] = array('file', $errorlog);
		} else {
			$descriptorspec[2] = array('pipe', 'w');
		}

		$option = array(
			'bypass_shell' => false,
			'blocking_pipes' => false,
			'create_new_console' => false,
			'create_process_group' => false,
		);

		$proc = proc_open($cmd, $descriptorspec, $pipes, null, null, $option);

		proc_close($proc);

	}

	// Windowsコマンド用に文字列をエスケープする関数
	// $exclude_ampersand を true に設定すると & をエスケープ対象から除外する
	function win_exec_escape($text, $exclude_ampersand = false){

		$text = str_replace('^', '^^', $text);
		if ($exclude_ampersand === false) $text = str_replace('&', '^&', $text); // ^ を ^^ にエスケープしてから実行
		$text = str_replace('<', '^<', $text);
		$text = str_replace('>', '^>', $text);
		$text = str_replace('|', '^|', $text);

		return $text;
	}

	// BOM除去
	function removeBOM($text){
		// 悪しきBOM
		$utf8bom = pack('H*', 'EFBBBF'); // UTF-8
		$utf16bom = pack('H*', 'FFFE'); // UTF-16

		// 置換して返す
		$text = preg_replace("/^$utf8bom/", '', $text);
		$text = preg_replace("/^$utf16bom/", '', $text);
		return $text;
	}

	// [字] などをhtmlで修飾する関数
	function decorateMark($string){

		// 参考：https://github.com/xtne6f/EDCB/blob/work-plus-s/EpgDataCap3/EpgDataCap3/ARIB8CharDecode.h
		//      https://ja.wikipedia.org/wiki/%E7%95%AA%E7%B5%84%E8%A1%A8
		//      http://www.motoyafont.jp/embedded-font/arib.html
		$marktable = array(
			'[新]','[終]','[再]','[交]','[映]','[手]','[声]','[多]','[副]','[字]','[文]','[CC]','[OP]','[二]','[S]','[B]','[SS]','[無]','[無料]',
			'[C]','[S1]','[S2]','[S3]','[MV]','[双]','[デ]','[D]','[N]','[W]','[P]','[H]','[HV]','[SD]','[天]','[解]','[料]','[前]','[後]',
			'[初]','[生]','[販]','[吹]','[PPV]','[演]','[移]','[他]','[収]','[・]','[英]','[韓]','[中]','[字/日英]','(二)','(字)','(再)',
			'[3D]','[2K]','[4K]','[8K]','[5.1]','[7.1]','[22.2]','[60P]','[120P]','[d]','[HC]','[HDR]','[SHV]','[UHD]','[VOD]','[配]'
		);
		
		foreach ($marktable as $value) {
			if (strpos($string, $value) !== false){
				$mark = str_replace('[', '', str_replace(']', '', $value)); // $value から [] を取る
				$mark = str_replace('(', '', str_replace(')', '', $mark)); // $mark から () を取る
				$string = str_replace($value, '<span class="mark">'.$mark.'</span>', $string); // 置換
			}
		}

		return $string; // 置換後の文字列を帰す
	}

	// basic認証関連
	// 若干時間がかかるため、index.php の読み込み時のみに実行する
	function basicAuth($basicauth, $basicauth_user, $basicauth_password){

		global $base_dir, $htaccess, $htpasswd;

		// basic認証有効
		if ($basicauth == 'true'){

			// .htpasswd ファイル作成
			$htpasswd_conf = $basicauth_user.':'.password_hash($basicauth_password, PASSWORD_BCRYPT);
			file_put_contents($htpasswd, $htpasswd_conf);

			// .htaccess 書き換え
			$htaccess_conf = file_get_contents($htaccess);

			// 記述がない場合は追加する
			if (!preg_match("/# Basic認証をかける.*/", $htaccess_conf)){

				// .htpasswd の絶対パスを修正
				$htaccess_conf = $htaccess_conf."\n".
					'# Basic認証をかける'."\n".
					'AuthType Basic'."\n".
					'AuthName "Input your ID and Password."'."\n".
					'AuthUserFile '.$base_dir.'htdocs/.htpasswd'."\n".
					'require valid-user'."\n";
				
				file_put_contents($htaccess, $htaccess_conf);
			}

		} else {

			// .htpasswd 削除
			if (file_exists($htpasswd)) unlink($htpasswd);

			// .htaccess の記述を削除
			$htaccess_conf = file_get_contents($htaccess);
			if (preg_match("/# Basic認証をかける.*/", $htaccess_conf)){
				$htaccess_conf = preg_replace("/# Basic認証をかける.*/s", '', $htaccess_conf);
				$htaccess_conf = rtrim($htaccess_conf, "\n")."\n";
				file_put_contents($htaccess, $htaccess_conf);
			}

		}
	}

	// URLからストリーム番号を取得する関数
	// flgをtrueにするとストリーム番号が指定されているかどうかを返す
	function getStreamNumber($url, $flg=false){

		// クエリを除外
		$url = parse_url($url, PHP_URL_PATH);

		// URLの最初と最後にあるかもしれないスラッシュと
		// v3/ を削除しておくのがポイント
		$slash = explode('/', str_replace('v3/', '', trim($url, '/')));

		// 配列の最後の値を取得
		$stream = end($slash);

		// URLに正しいストリーム番号が入っていなかった場合はストリーム1とする
		if (is_empty($stream) or !is_numeric($stream)){
			$stream = 1;
			$stream_flg = false;
		} else {
			$stream_flg = true;
		}

		if (!$flg){
			return strval($stream);
		} else {
			return $stream_flg;
		}
	}

	// ストリーム番号から現在実行中の TSTask の PID を取得する関数
	// 見つからなかった場合は -1 を返す
	function getTSTaskPID($stream) {

		global $udp_port, $tstask_exe;

		// 数値に変換しておく
		$stream = intval($stream);

		// ストリームの配信ポート
		$stream_port = $udp_port + $stream;

		// 超ゴリ押し PowerShell スクリプトをコマンドプロンプトから実行
		$command = 'powershell -Command "'.
		           // CSV がコンソール幅で改行されないようにコンソール幅を 9999 に設定
		           '$h = get-host; $w = $h.ui.rawui; $n = $w.buffersize; $n.Width = 9999; $w.buffersize = $n; '.
		           // Get-WmiObject で引数（コマンドライン）つきのプロセスリストを取得
		           'Get-WmiObject win32_process | '.
		           // cmd.exe から実行されているプロセスを除外
		           '? { $_.CommandLine -notlike \'*cmd.exe*\' } | '.
		           // コマンドラインに TSTask とポート番号が含まれているプロセスのみに絞り込み
		           '? { $_.CommandLine -like \'*'.$tstask_exe.'*'.$stream_port.'*\' } | '.
		           // プロセス ID とコマンドラインのみ取得
		           'Select-Object ProcessId, CommandLine | '.
		           // ConvertTo-CSV で tsv に変換
		           'ConvertTo-Csv -NoTypeInformation  -Delimiter `t "';

		exec($command, $result);

		// CSV をパースして二次元配列に
		$tstask_list = [];
		foreach ($result as $index => $line) {

			// ヘッダー
			if ($index === 0) {
				$tstask_list_header = str_getcsv(removeBOM($line), "\t"); // 悪しき BOM を除去
				continue; // その後の処理をスキップ
			}

			// ヘッダー分のインデックスを詰める
			$index = $index - 1; 

			// データを連想配列に格納
			foreach (str_getcsv($line, "\t") as $key => $value) {
				$tstask_list[$index][$tstask_list_header[$key]] = $value;
			}
		}

		if (isset($tstask_list[0]['ProcessId'])) {
			// PID を取得できた
			return intval($tstask_list[0]['ProcessId']);
		} else {
			// PID を取得できなかった
			return -1;
		}

	}

	// ストリーム状態を整形して返す関数
	function getFormattedState($ini, $num, $flg=false){
		
		$num = strval($num);

		if (isset($ini[$num])){
			if ($ini[$num]['state'] == 'ONAir'){
				$format = 'ON Air';
			} else if ($ini[$num]['state'] == 'File'){
				$format = 'File';
			} else {
				$format = 'Offline';
			}
		} else {
			$format = 'Offline';
		}

		if ($flg){
			return $format;
		} else {
			return '● '.$format;
		}
	}

	// ストリームかアクティブかどうかを返す関数
	function isStreamActive($ini, $num, $default = false){

		$num = strval($num);

		if (isset($ini[$num])){
			if ($ini[$num]['state'] == 'ONAir' or $ini[$num]['state'] == 'File'){
				return true;
			} else {
				return false;
			}
		} else {
			// 値が存在しない場合に返す真偽値
			if ($default){
				return true;
			} else {
				return false;
			}
		}
	}

	// Cookie 内の設定に指定の項目が指定された値であるかどうかを確認する関数
	// あれば true・ないもしくは設定自体が存在しない場合は false を返す
	// match を省略した場合はその項目の値を返す
	// default には設定のキーが存在しない場合のデフォルト値を設定できる
	function isSettingsItem($item, $match = null, $default = false){

		// Cookieが存在する
		if (isset($_COOKIE['settings'])){

			// 設定内容を読み込み
			$settings = json_decode($_COOKIE['settings'], true);

			if (isset($settings[$item])){
				if ($settings[$item] === $match){
					return true;
				} else if ($match === null){ // === にしないとおかしくなることがある
					return $settings[$item];
				} else {
					return false;
				}
			} else {
				return $default; // default に指定した値を返す
			}
			
		// Cookie が存在しない
		} else {
			return $default; // default に指定した値を返す
		}
	}

	// デフォルトの動画の画質を取得する関数
	// 個人設定があれば個人設定を優先する
	function getQualityDefault() {

		global $quality_default;

		// 個人設定がある
		if (isSettingsItem('quality_user_default')) {
			// 「環境設定を引き継ぐ」以外なら個人設定を使う
			if (isSettingsItem('quality_user_default') !== 'environment') {
				return isSettingsItem('quality_user_default');
			// 環境設定を使う
			} else {
				return $quality_default;
			}
		// 環境設定を使う
		} else {
			return $quality_default;
		}
	}

	// 局ロゴの URL を取得する関数
	function getLogoURL($channel) {

		global $onid, $sid; // オブジェクト指向じゃないので global を使う羽目に…

		// 設定がオンになっている場合のみ
		if (isSettingsItem('logo_show', true, true)) {

			// 局ロゴ API の URL を返す
			return '/api/logo?onid='.$onid[strval($channel)].'&sid='.$sid[strval($channel)];

		} else {

			return ''; // 空
		}
	}

	// CSVファイルを読み込む関数
	function getCSV($csvfile, $encoding='UTF-16LE'){

		// ファイル存在確認
		if (!file_exists($csvfile)) return false;

		// 行頭と行末の改行・BOM削除・UTF-8へ変換
		file_put_contents($csvfile, str_replace('yadif=0:-1:1,', 'yadif=0:-1:1.', trim(removeBOM(mb_convert_encoding(file_get_contents($csvfile), 'UTF-8', $encoding)))));
	
		// SplFileObject()を使用してCSVをロード
		$file = new SplFileObject($csvfile);
		$file->setFlags(
			SplFileObject::READ_CSV |
			SplFileObject::SKIP_EMPTY |
			SplFileObject::READ_AHEAD
		);
	
		// 各行を処理
		$records = array();
		foreach ($file as $i => $row){

			// 1行目はキーヘッダ行として取り込み
			if ($i === 0) {
				foreach($row as $j => $col) $colbook[$j] = $col;
				continue;
			}
	
			// 2行目以降はデータ行として取り込み
			$line = array();
			foreach($colbook as $j => $col) $line[$colbook[$j]] = @$row[$j];
			$records[] = $line;
		}
		return $records;
	}

	// 録画情報ファイル (.ts.program.txt) を解析して配列に格納する関数
	function programToArray($program_file){

		// .ts.program.txt を取得
		$program_data = removeBOM(file_get_contents($program_file));

		// 文字コード判定
		if (!empty(mb_detect_encoding($program_data, 'SJIS, SJIS-WIN, EUC-JP, UTF-8'))){
			$charset = mb_detect_encoding($program_data, 'SJIS, SJIS-WIN, EUC-JP, UTF-8');
		} else { // 何故かUTF-16だけ上手く検知されないバグが…
			$charset = 'UTF-16LE';
		}

		// 念のため文字コードをUTF-8に
		if ($charset != 'UTF-8') $program_data = mb_convert_encoding($program_data, 'UTF-8', $charset);

		// 改行で分割して配列にする
		// ついでに全角英数字を半角に変換
		$program_array = explode("\r\n", mb_convert_kana($program_data, 'asv', 'UTF-8'));

		// 行ごとに処理
		foreach ($program_array as $key => $value) {

			switch ($key){

				case 0:
					$program['time'] = $value;
					$program['date'] = mb_substr($value, 0, 10);
					$program['start'] = mb_substr($value, 14, 5);
					$program['start_timestamp'] = strtotime($program['date'].' '.$program['start']);
					$program['end'] = mb_substr($value, 20, 5);
					$program['end_timestamp'] = strtotime($program['date'].' '.$program['end']);
					// start_timestamp よりも end_timestamp の方が小さい場合は日付を跨いだと計算し1日足す
					if ($program['start_timestamp'] > $program['end_timestamp']) $program['end_timestamp'] += 86400;
					$program['duration'] = intval(round(($program['end_timestamp'] - $program['start_timestamp']) / 60));
					break;

				case 1:
					$program['channel'] = $value;
					break;

				case 2:
					$program['title'] = $value;
					break;

				case 3:
					// 空行
					break;

				case 4:
					$program['info'] = $value;
					break;

				case 5:
					// 空行
					break;

				default:

					// ジャンル : 以降はいらない情報なので foreach ごとループを抜ける
					if ($value == 'ジャンル : ') break 2;

					// 定義しておく
					if (!isset($program['description'])) $program['description'] = '';

					// ジャンル : が現れるまで足し続ける 
					$program['description'] = $program['description']."\n".$value;
					
					break;
			}
		}

		// 末尾の改行を除去
		if (isset($program['description'])){
			$program['description'] = rtrim($program['description'], "\n");
		} else {
			$program['description'] = '';
		}

		return $program;
	}

	// ch2を解析して連想配列化する関数
	function ch2ToArray($ch2_file, $flg){

		// ch2を取得
		$ch2_rawdata = removeBOM(file_get_contents($ch2_file));

		// 文字コード判定
		if (!empty(mb_detect_encoding($ch2_rawdata, 'SJIS, SJIS-WIN, EUC-JP, UTF-8'))){
			$charset = mb_detect_encoding($ch2_rawdata, 'SJIS, SJIS-WIN, EUC-JP, UTF-8');
		} else { // 何故かUTF-16だけ上手く検知されないバグが…
			$charset = 'UTF-16LE';
		}

		// ch2の文字コードをUTF-8に
		if ($charset != 'UTF-8'){
			$ch2_data = mb_convert_encoding($ch2_rawdata, 'UTF-8', $charset);
		} else {
			$ch2_data = $ch2_rawdata;
		}

		// 置換
		$ch2_data = str_replace("\r\n", "\n", $ch2_data); // CR+LFからLFに変換
		$ch2_data = str_replace("; TVTest チャンネル設定ファイル\n", "", $ch2_data);
		$ch2_data = preg_replace("/; 名称,チューニング空間.*/", "", $ch2_data);
		$ch2_data = str_replace(',,', ',1,', $ch2_data); // サービスタイプ欄がない場合に1として換算しておく

		// トランスモジュレーションの場合
		if (strpos($ch2_data, 'TransModulation') !== false){

			//余計なコメントを削除
			$ch2_data = preg_replace("/;#SPACE.*/", "", $ch2_data);
			
			// 空行削除
			$ch2_data = str_replace("\n\n", '', $ch2_data);
			$ch2_data = rtrim($ch2_data);
	
			// 改行で分割
			$ch2 = explode("\n", $ch2_data);
	
			// さらにコンマで分割
			foreach ($ch2 as $key => $value) {
				$line = explode(',', $ch2[$key]);
				if ($flg == 'UHF'){
					if (intval($line[6]) > 30000 and intval($line[6]) < 40000){
						$ch2[$key] = $line;
					}
				} else if ($flg == 'BS'){
					if (intval($line[6]) == 4){
						$ch2[$key] = $line;
					}
				} else if ($flg == 'CS'){
					if (intval($line[6]) == 65534){
						$ch2[$key] = $line;
					}
				} else if ($flg == 'SPHD'){
					if (intval($line[6]) == 10){
						$ch2[$key] = $line;
					}
				} else if ($flg == 'SPSD'){
					if (intval($line[6]) == 1){
						$ch2[$key] = $line;
					}
                                }
			}

		// 通常
		} else {

			// 地上波
			if ($flg == 'UHF'){
				$ch2_data = preg_replace("/;#SPACE\(.\,BS\).*$/s", "", $ch2_data); // BS・CSを削除
			// BS
			} else if ($flg == 'BS'){
				if (preg_match("/;#SPACE\(.\,BS\)/", $ch2_data)){
					$ch2_data = preg_replace("/;#SPACE\(.\,(UHF|GR|地上D)\).*;#SPACE\(.\,BS\)/s", "", $ch2_data); // 地上波を削除
					$ch2_data = preg_replace("/;#SPACE\(.\,(CS|CS110)\).*$/s", "", $ch2_data); // CSを削除
				} else {
					$ch2_data = '';
				}
			// CS
			} else if ($flg == 'CS') {
				if (preg_match("/;#SPACE\(.\,(CS|CS110)\)/", $ch2_data)){
					$ch2_data = preg_replace("/;#SPACE\(.\,(UHF|GR|地上D)\).*;#SPACE\(.\,(CS|CS110)\)/s", "", $ch2_data); // 地上波・BSを削除（混合チューナー用）
					$ch2_data = preg_replace("/;#SPACE\(.\,BS\).*;#SPACE\(.\,(CS|CS110)\)/s", "", $ch2_data); // BSを削除
					$ch2_data = preg_replace("/;#SPACE\(.\,(スカパー|SKY|HIKARI)\).*$/s", "", $ch2_data); // スカパー以降を削除
				} else {
					$ch2_data = '';
				}
			} else if ($flg == 'SPHD') {
				if (preg_match("/;#SPACE\(.\,(スカパー|SKY|HIKARI)\)/", $ch2_data)){
					$ch2_data = preg_replace("/;#SPACE\(.\,(UHF|GR|地上D)\).*;#SPACE\(.\,(スカパー|SKY|HIKARI)\)/s", "", $ch2_data); // スカパー以外を削除削除（混合チューナー用）
					$ch2_data = preg_replace("/;#SPACE\(.\,(UHF|GR|地上D)\).*;#SPACE\(.\,BS\)/s", "", $ch2_data); // 地上波を削除
					$ch2_data = preg_replace("/;#SPACE\(.\,BS\).*;#SPACE\(.\,(CS|CS110)\)/s", "", $ch2_data); // BSを削除
					$ch2_data = preg_replace("/;#SPACE\(.\,(CS|CS110).*;#SPACE\(.\,(スカパー|SKY)\)/s", "", $ch2_data); // CSを削除
				} else {
					$ch2_data = '';
				}
                        } else if ($flg == 'SPSD') {
				if (preg_match("/;#SPACE\(.\,(スターデジオ|StarDigio)\)/", $ch2_data)){ // スターデジオで専用のスペースがあるときはこっち
					$ch2_data = preg_replace("/;#SPACE\(.\,(UHF|GR|地上D)\).*;#SPACE\(.\,(スターデジオ|StarDigio)\)/s", "", $ch2_data); // スターデジオ以外を削除を削除（混合チューナー用）
					$ch2_data = preg_replace("/;#SPACE\(.\,(UHF|GR|地上D)\)/s", "", $ch2_data); // 地上波を削除
					$ch2_data = preg_replace("/;#SPACE\(.\,BS\).*;#SPACE\(.\,(CS|CS110)\)/s", "", $ch2_data); // BSを削除
					$ch2_data = preg_replace("/;#SPACE\(.\,(CS|CS110)\).*;#SPACE\(.\,(スターデジオ|StarDigio)\)/s", "", $ch2_data); // CSを削除
				} else if (preg_match("/;#SPACE\(.\,(スカパー|SKY|HIKARI)\)/", $ch2_data)){ // スターデジオをスカパーがくっついてるときに使う
					$ch2_data = preg_replace("/;#SPACE\(.\,(UHF|GR|地上D)\).*;#SPACE\(.\,(スカパー|SKY|HIKARI)\)/s", "", $ch2_data); // スカパー以外を削除削除（混合チューナー用）
					$ch2_data = preg_replace("/;#SPACE\(.\,(UHF|GR|地上D)\).*;#SPACE\(.\,BS\)/s", "", $ch2_data); // 地上波を削除
					$ch2_data = preg_replace("/;#SPACE\(.\,BS\).*;#SPACE\(.\,(CS|CS110)\)/s", "", $ch2_data); // BSを削除
					$ch2_data = preg_replace("/;#SPACE\(.\,(CS|CS110)\).*;#SPACE\(.\,(スカパー|SKY)\)/s", "", $ch2_data); // CSを削除
				} else {
					$ch2_data = '';
				}
			}

			//余計なコメントを削除
			$ch2_data = preg_replace("/;#SPACE.*/", "", $ch2_data);

			// 空行削除
			$ch2_data = str_replace("\n\n", '', $ch2_data);
			$ch2_data = rtrim($ch2_data);
	
			// 改行で分割
			$ch2 = explode("\n", $ch2_data);
	
			// さらにコンマで分割
			foreach ($ch2 as $key => $value) {
				$ch2[$key] = explode(',', $ch2[$key]);
			}
		}

		return $ch2;
	}

	// ch2からチャンネルリストとサービスIDリストを取得する関数
	function initBonChannel($BonDriver_dir){

		// BonDriver_dirからBonDriverを検索
		foreach (glob($BonDriver_dir.'[bB]on[dD]river_*.dll') as $i => $file) {
			$BonDriver_dll[$i] = str_replace($BonDriver_dir, '', $file);
		}
		if (!isset($BonDriver_dll)) $BonDriver_dll = array();
	
		// BonDriver_dirから地デジ用BonDriverを検索
		$search_T = array_merge(
			glob($BonDriver_dir.'[bB]on[dD]river_*[tT].dll'),
			glob($BonDriver_dir.'[bB]on[dD]river_*_[tT][0-9]*.dll'),
			glob($BonDriver_dir.'[bB]on[dD]river_*-[tT][0-9]*.dll')
		);
		foreach ($search_T as $i => $file) {
			$BonDriver_dll_T[$i] = str_replace($BonDriver_dir, '', $file);
		}
		if (!isset($BonDriver_dll_T)) $BonDriver_dll_T = array();

		// BonDriver_dirからBSCS用BonDriverを検索
		$search_S = array_merge(
			glob($BonDriver_dir.'[bB]on[dD]river_*[sS].dll'),
			glob($BonDriver_dir.'[bB]on[dD]river_*_[sS][0-9]*.dll'),
			glob($BonDriver_dir.'[bB]on[dD]river_*-[sS][0-9]*.dll')
		);
		foreach ($search_S as $i => $file) {
			$BonDriver_dll_S[$i] = str_replace($BonDriver_dir, '', $file);
		}
		if (!isset($BonDriver_dll_S)) $BonDriver_dll_S = array();

		// BonDriver_dirからスカパー！用BonDriverを検索
		$search_SPHD = array_merge(
			glob($BonDriver_dir.'[bB]on[dD]river_*[phdPHD].dll'),
			glob($BonDriver_dir.'[bB]on[dD]river_*_[phdPHD][0-9]*.dll'),
			glob($BonDriver_dir.'[bB]on[dD]river_*-[phdPHD][0-9]*.dll')
		);
		foreach ($search_SPHD as $i => $file) {
			$BonDriver_dll_SPHD[$i] = str_replace($BonDriver_dir, '', $file);
		}
		if (!isset($BonDriver_dll_SPHD)) $BonDriver_dll_SPHD = array();


		// 無印BonDriverを洗い出す
		$BonDriver_dll_raw = $BonDriver_dll;
		foreach ($BonDriver_dll as $key => $value) {
			foreach ($BonDriver_dll_T as $key1 => $value1) {
				if ($value === $value1){
					unset($BonDriver_dll_raw[$key]);
				}
			}
			foreach ($BonDriver_dll_S as $key2 => $value2) {
				if ($value === $value2){
					unset($BonDriver_dll_raw[$key]);
				}
			}
			foreach ($BonDriver_dll_SPHD as $key2 => $value2) {
				if ($value === $value2){
					unset($BonDriver_dll_raw[$key]);
				}
			}
		}
		
		// 配列のインデックスを詰める
		$BonDriver_dll_raw = array_values($BonDriver_dll_raw);

		// 無印BonDriverを配列の末尾に足す
		$BonDriver_dll_T = array_merge($BonDriver_dll_T, $BonDriver_dll_raw);
		$BonDriver_dll_S = array_merge($BonDriver_dll_S, $BonDriver_dll_raw);
		$BonDriver_dll_SPHD = array_merge($BonDriver_dll_SPHD, $BonDriver_dll_raw);


		// ch2を検索する
		// 地デジ用
		$BonDriver_ch2_file_T = array_merge(
			glob($BonDriver_dir.'[bB]on[dD]river_*[tT].ch2'),
			glob($BonDriver_dir.'[bB]on[dD]river_*_[tT][0-9]*.ch2'),
			glob($BonDriver_dir.'[bB]on[dD]river_*-[tT][0-9]*.ch2')
		);

		// BS・CS用
		$BonDriver_ch2_file_S = array_merge(
			glob($BonDriver_dir.'[bB]on[dD]river_*[sS].ch2'),
			glob($BonDriver_dir.'[bB]on[dD]river_*_[sS][0-9]*.ch2'),
			glob($BonDriver_dir.'[bB]on[dD]river_*-[sS][0-9]*.ch2')
		);

		// スカパー！用
		$BonDriver_ch2_file_SPHD = array_merge(
			glob($BonDriver_dir.'[bB]on[dD]river_*[phdPHD].ch2'),
			glob($BonDriver_dir.'[bB]on[dD]river_*_[phdPHD][0-9]*.ch2'),
			glob($BonDriver_dir.'[bB]on[dD]river_*-[phdPHD][0-9]*.ch2')
		);

		// その他（混合チューナー用）
		$BonDriver_ch2_file_raw = glob($BonDriver_dir.'[bB]on[dD]river_*.ch2');


		// 地デジのch2があれば
		// 地デジ用もBS・CS用もないが無印ch2はある場合も含める（混合チューナー用）
		if (!empty($BonDriver_ch2_file_T) || (empty($BonDriver_ch2_file_T) && empty($BonDriver_ch2_file_S) && !empty($BonDriver_ch2_file_raw))){

			// ch2を連想配列に変換
			$BonDriver_ch2_T = ch2ToArray(array_merge($BonDriver_ch2_file_T, $BonDriver_ch2_file_raw)[0], 'UHF');

			if (!empty($BonDriver_ch2_T[0][0])){

				// 地デジ(T)用チャンネルをセット
				foreach ($BonDriver_ch2_T as $key => $value) {

					// サービス状態が1の物のみセットする
					// あとワンセグ(192)・データ放送(192)・ラジオチャンネル(2)・サブチャンネルはセットしない
					if ($value[4] != 192 and $value[8] == 1){
						// 全角は半角に直す
						// 衝突回避でリモコン番号が衝突したら元番号 + 20にする
						if (empty($ch_T[strval($value[3] . '_1')])){
							// チャンネル名
							$ch_T[strval($value[3] . '_1')] = mb_convert_kana($value[0], 'asv');
							// サービスID(SID)
							$sid_T[strval($value[3] . '_1')] = mb_convert_kana($value[5], 'asv');
							// ネットワークID(NID・ONID)
							$onid_T[strval($value[3] . '_1')] = mb_convert_kana($value[6], 'asv');
							// トランスポートストリームID(TSID)
							$tsid_T[strval($value[3] . '_1')] = mb_convert_kana($value[7], 'asv');
						// 衝突した場合
						} else {
							// 20以降のリモコン番号にしさらに被ってたら+20する
							$chcount = 20;
							while(!empty($ch_T[strval($value[3] + $chcount)])){
								$chcount += 20; // 足す
							}
							// チャンネル名
							$ch_T[strval($value[3] + $chcount . '_1')] = mb_convert_kana($value[0], 'asv');
							// サービスID(SID)
							$sid_T[strval($value[3] + $chcount . '_1')] = mb_convert_kana($value[5], 'asv');
							// ネットワークID(NID・ONID)
							$onid_T[strval($value[3] + $chcount . '_1')] = mb_convert_kana($value[6], 'asv');
							// トランスポートストリームID(TSID)
							$tsid_T[strval($value[3] + $chcount . '_1')] = mb_convert_kana($value[7], 'asv');
						}
					// サブチャンネルをセット (サブチャンネル表示がオンになっている場合のみ)
					} else if ($value[4] != 192 and $value[4] != 2 and isSettingsItem('subchannel_show', true)){
						// リモコン番号が被らないよう 6_2・6_3 のようにする
						$subchcount = 2;
						while(!empty($ch_T[strval($value[3] .'_'. $subchcount)])){
							$subchcount++; // 足す
						}
						// チャンネル名
						$ch_T[strval($value[3] .'_'. $subchcount)] = mb_convert_kana($value[0], 'asv');
						// サービスID(SID)
						$sid_T[strval($value[3] .'_'. $subchcount)] = mb_convert_kana($value[5], 'asv');
						// ネットワークID(NID・ONID)
						$onid_T[strval($value[3] .'_'. $subchcount)] = mb_convert_kana($value[6], 'asv');
						// トランスポートストリームID(TSID)
						$tsid_T[strval($value[3] .'_'. $subchcount)] = mb_convert_kana($value[7], 'asv');
					}
				}

			} else {
				$ch_T = array();
				$sid_T = array();
				$onid_T = array();
				$tsid_T = array();
			}

		} else {
			$ch_T = array();
			$sid_T = array();
			$onid_T = array();
			$tsid_T = array();
		}

		// BSCSのch2があれば
		// 地デジ用もBS・CS用もないが無印ch2はある場合も含める（混合チューナー用）
		if (!empty($BonDriver_ch2_file_S) || (empty($BonDriver_ch2_file_T) && empty($BonDriver_ch2_file_S) && !empty($BonDriver_ch2_file_raw))){

			// ch2を連想配列に変換
			$BonDriver_ch2_S = ch2ToArray(array_merge($BonDriver_ch2_file_S, $BonDriver_ch2_file_raw)[0], 'BS');
			$BonDriver_ch2_CS = ch2ToArray(array_merge($BonDriver_ch2_file_S, $BonDriver_ch2_file_raw)[0], 'CS');

			if (!empty($BonDriver_ch2_S[0][0])){

				// BS用チャンネルをセット
				foreach ($BonDriver_ch2_S as $key => $value) {

					// サービス状態が1の物のみセットする
					// あとワンセグ(192)・データ放送(192)・ラジオチャンネル(2)はセットしない
					// サブチャンネルはサブチャンネル表示がオンになっている場合のみ
					if ($value[4] != 192 and
						(($value[8] == 1 and !isset($ch_S[strval($value[5])])) or isSettingsItem('subchannel_show', true))){
						// 全角は半角に直す
						// チャンネル名
						$ch_S[strval($value[5])] = mb_convert_kana($value[0], 'asv');
						// サービスID(SID)
						$sid_S[strval($value[5])] = mb_convert_kana($value[5], 'asv');
						// ネットワークID(NID・ONID)
						$onid_S[strval($value[5])] = mb_convert_kana($value[6], 'asv');
						// トランスポートストリームID(TSID)
						$tsid_S[strval($value[5])] = mb_convert_kana($value[7], 'asv');
					}
				}

			} else {
				$ch_S = array();
				$sid_S = array();
				$onid_S = array();
				$tsid_S = array();
			}

			if (!empty($BonDriver_ch2_CS[0][0])){

				// CS用チャンネルをセット
				foreach ($BonDriver_ch2_CS as $key => $value) {
					// サービス状態が1の物のみセットする
					// あとワンセグ(192)・データ放送(192)・ラジオチャンネル(2)はセットしない
					// サブチャンネルはサブチャンネル表示がオンになっている場合のみ
					if ($value[4] != 192 and
						(($value[8] == 1 and !isset($ch_CS[strval($value[5])])) or isSettingsItem('subchannel_show', true))){
						// 全角は半角に直す
						// BS-TBSとQVCバッティング問題
						if (intval($value[5]) !== 161){
							// チャンネル名
							$ch_CS[strval($value[5])] = mb_convert_kana($value[0], 'asv');
							// サービスID(SID)
							$sid_CS[strval($value[5])] = mb_convert_kana($value[5], 'asv');
							// ネットワークID(NID・ONID)
							$onid_CS[strval($value[5])] = mb_convert_kana($value[6], 'asv');
							// トランスポートストリームID(TSID)
							$tsid_CS[strval($value[5])] = mb_convert_kana($value[7], 'asv');
						// QVCのみ (チャンネル衝突避け)
						} else {
							// チャンネル名
							$ch_CS['161cs'] = mb_convert_kana($value[0], 'asv');
							// サービスID(SID)
							$sid_CS['161cs'] = mb_convert_kana($value[5], 'asv');
							// ネットワークID(NID・ONID)
							$onid_CS['161cs'] = mb_convert_kana($value[6], 'asv');
							// トランスポートストリームID(TSID)
							$tsid_CS['161cs'] = mb_convert_kana($value[7], 'asv');
						}
					}
				}

			} else {
				$ch_CS = array();
				$sid_CS = array();
				$onid_CS = array();
				$tsid_CS = array();
			}

		} else {
			$ch_S = array();
			$ch_CS = array();
			$sid_S = array();
			$sid_CS = array();
			$onid_S = array();
			$onid_CS = array();
			$tsid_S = array();
			$tsid_CS = array();
		}

		// スカパーのch2があれば
		// スカパー用はないが無印ch2はある場合も含める（混合チューナー用）
		if (!empty($BonDriver_ch2_file_SPHD) || empty($BonDriver_ch2_file_SPHD) && !empty($BonDriver_ch2_file_raw)){

			// ch2を連想配列に変換
			$BonDriver_ch2_SPHD = ch2ToArray(array_merge($BonDriver_ch2_file_SPHD, $BonDriver_ch2_file_raw)[0], 'SPHD');
			$BonDriver_ch2_SPSD = ch2ToArray(array_merge($BonDriver_ch2_file_SPHD, $BonDriver_ch2_file_raw)[0], 'SPSD');

			if (!empty($BonDriver_ch2_SPHD[0][0])){

				// スカパー用チャンネルをセット
				foreach ($BonDriver_ch2_SPHD as $key => $value) {

					// サービス状態が1の物のみセットする
					// あとワンセグ(192)・データ放送(192)・ラジオチャンネル(2)はセットしない
					// サブチャンネルはサブチャンネル表示がオンになっている場合のみ
					if ($value[4] != 192 and $value[3] >= 500 and
						(($value[8] == 1 and !isset($ch_SPHD[strval($value[3])])) ) ){
						// 全角は半角に直す
						// チャンネル名
						$ch_SPHD[strval($value[3])] = mb_convert_kana($value[0], 'asv');
						// サービスID(SID)
						$sid_SPHD[strval($value[3])] = mb_convert_kana($value[5], 'asv');
						// ネットワークID(NID・ONID)
						$onid_SPHD[strval($value[3])] = mb_convert_kana($value[6], 'asv');
						// トランスポートストリームID(TSID)
						$tsid_SPHD[strval($value[3])] = mb_convert_kana($value[7], 'asv');
					} 
				}

			} else {
				$ch_SPHD = array();
				$sid_SPHD = array();
				$onid_SPHD = array();
				$tsid_SPHD = array();
			}

			if (!empty($BonDriver_ch2_SPSD[0][0])){

				// SPSD用チャンネルをセット
				foreach ($BonDriver_ch2_SPSD as $key => $value) {
					// サービス状態が1の物のみセットする
					// あとワンセグ(192)・データ放送(192)・ラジオチャンネル(2)はセットしない
					// サブチャンネルはサブチャンネル表示がオンになっている場合のみ
					if ($value[4] != 192 and $value[3] <= 499 and
						(($value[8] == 1 and !isset($ch_SPSD[strval($value[3])])) )  ){
						// 全角は半角に直す
							// チャンネル名
							$ch_SPSD[strval($value[5])] = mb_convert_kana($value[0], 'asv');
							// サービスID(SID)
							$sid_SPSD[strval($value[5])] = mb_convert_kana($value[5], 'asv');
							// ネットワークID(NID・ONID)
							$onid_SPSD[strval($value[5])] = mb_convert_kana($value[6], 'asv');
							// トランスポートストリームID(TSID)
							$tsid_SPSD[strval($value[5])] = mb_convert_kana($value[7], 'asv');
							// SPSDの時は仕組み上の関係でエラーするのでからデータを返す
					} else {
						    $ch_SPSD = array();
						    $sid_SPSD = array();
						    $onid_SPSD = array();
						    $tsid_SPSD = array();
					}
				}

			} else {
				$ch_SPSD = array();
				$sid_SPSD = array();
				$onid_SPSD = array();
				$tsid_SPSD = array();
			}

		} else {
			$ch_SPHD = array();
			$sid_SPHD = array();
			$onid_SPHD = array();
			$tsid_SPHD = array();
			$ch_SPSD = array();
			$sid_SPSD = array();
			$onid_SPSD = array();
			$tsid_SPSD = array();
		}

		// 合体させる
		$ch = $ch_T + $ch_S + $ch_CS + $ch_SPHD + $ch_SPSD;
		$sid = $sid_T + $sid_S + $sid_CS + $sid_SPHD + $sid_SPSD;
		$onid = $onid_T + $onid_S + $onid_CS + $onid_SPHD + $onid_SPSD;
		$tsid = $tsid_T + $tsid_S + $tsid_CS + $tsid_SPHD + $tsid_SPSD;

		return array($BonDriver_dll, $BonDriver_dll_T, $BonDriver_dll_S,$BonDriver_dll_SPHD, // BonDriver
					 $ch, $ch_T, $ch_S, $ch_CS, $ch_SPHD, $ch_SPSD, // チャンネル番号
					 $sid, $sid_T, $sid_S, $sid_CS, $sid_SPHD, $sid_SPSD, // SID
					 $onid, $onid_T, $onid_S, $onid_CS, $onid_SPHD, $onid_SPSD, // ONID(NID)
					 $tsid, $tsid_T, $tsid_S, $tsid_CS, $tsid_SPHD, $tsid_SPSD); // TSID
	}
