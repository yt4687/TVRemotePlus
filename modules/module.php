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

	// ディレクトリを (再帰的に) スキャンして正規表現にマッチするファイルを返す
	function scandir_and_match_files($dir, $pattern = null, $depth = 1) {
		$dir = realpath($dir);
		if ($dir !== false && ($dh = opendir($dir))) {
			$ret = array();
			while (($file = readdir($dh)) !== false) {
				if ($file !== '.' && $file !== '..') {
					$path = $dir.DIRECTORY_SEPARATOR.$file;
					if (!is_dir($path)) {
						if (!isset($pattern) || preg_match($pattern, $file)) {
							$ret[] = $file;
						}
					} elseif ($depth > 1) {
						$sub = scandir_and_match_files($path, $pattern, $depth - 1);
						if ($sub !== false) {
							foreach ($sub as $v) {
								$ret[] = $file.'/'.$v;
							}
						}
					}
				}
			}
			closedir($dh);
			return $ret;
		}
		return false;
	}

	// 共有ロックでファイルの内容を読み込む
	function file_get_contents_lock_sh($path) {
		$s = false;
		$fp = @fopen($path, 'r');
		if ($fp) {
			if (flock($fp, LOCK_SH)) {
				$s = stream_get_contents($fp);
			}
			fclose($fp);
		}
		return $s;
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

			// 更新が必要なら
			$htpasswd_conf = @file_get_contents($htpasswd);
			if ($htpasswd_conf === false ||
			    strncmp($htpasswd_conf, $basicauth_user.':', strlen($basicauth_user) + 1) !== 0 ||
			    !password_verify($basicauth_password, substr($htpasswd_conf, strlen($basicauth_user) + 1))) {

				// .htpasswd ファイル作成
				$htpasswd_conf = $basicauth_user.':'.password_hash($basicauth_password, PASSWORD_BCRYPT);
				file_put_contents($htpasswd, $htpasswd_conf);
			}

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
		$stream = filter_var(end($slash), FILTER_VALIDATE_INT);

		// URLに正しいストリーム番号が入っていなかった場合はストリーム1とする
		if ($stream === false or $stream < 1 or $stream > 99){
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
		if (isset($_COOKIE['tvrp_settings'])){

			// 設定内容を読み込み
			$settings = json_decode($_COOKIE['tvrp_settings'], true);

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

		global $ch; // オブジェクト指向じゃないので global を使う羽目に…

		// 設定がオンになっている場合のみ
		if (isSettingsItem('logo_show', true, true)) {

			// 局ロゴ API の URL を返す
			return '/api/logo?onid='.$ch[$channel]['onid'].'&sid='.$ch[$channel]['sid'];

		} else {

			return ''; // 空
		}
	}

	// 録画情報ファイル (.ts.program.txt) を解析して配列に格納する関数
	function programToArray($program_data){

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

	// チャンネルリストを取得する関数
	function initBonChannel($cmd): array {

		// EPG のサービス一覧を取得
		$services = [];
		foreach ($cmd->sendEnumService() ?? [] as $v) {
			$services[$v['onid'].'-'.$v['tsid'].'-'.$v['sid']] = $v;
		}

		// ChSet5.txt を取得
		$chset5 = str_replace("\r\n", "\n", detectBomAndConvertEncoding($cmd->sendFileCopy('ChSet5.txt') ?? '', 'CP932') ?? '');

		// 改行でループ
		$ch = [];
		foreach (explode("\n", $chset5) as $v) {
			// コメントでなければ
			if (strncmp($v, ';', 1) !== 0) {
				// タブで分割
				$a = explode("\t", $v);
				// サービス名|ネットワーク名|ONID|TSID|SID|サービスタイプ|部分受信か|EPGデータ取得対象か|デフォルト検索対象か
				if (count($a) >= 9) {
					$onid = (int)$a[2];
					$tsid = (int)$a[3];
					$sid = (int)$a[4];
					// ワンセグ(192)・データ放送(192)・ラジオチャンネル(2)でなく、地上波か15未満の ONID をもつサービス
					// サブチャンネルはサブチャンネル表示がオンになっている場合のみ。BSは101から189まではメインチャンネル以外をサブチャンネルとして格納するようにする(103は条件づけできないので直接書いた)
					// サービス番号が0以外のものをサブチャンネルとする
					if ((int)$a[5] !== 192 && (int)$a[5] !== 165 && (int)$a[5] !== 2 &&
					    ((0x7880 <= $onid && $onid <= 0x7FEF) || $onid < 15 && 
						($onid !=4 || $onid ===4 && ($sid > 190 || $sid === 103 || substr($sid , -1) === '1') || isSettingsItem('subchannel_show', true))) &&
					    ($onid < 15 || isSettingsItem('subchannel_show', true) || $sid % 8 === 0 )) {

						// 地上波の SID が重複することはないので便宜上、ONID=15 とみなしてキーにする (全チャンネルを整数で識別できるのが利点)
						$chkey = ($onid < 15 ? $onid : 15) * 0x10000 + $sid;
						$ch[$chkey] = ['onid' => $onid, 'tsid' => $tsid, 'sid' => $sid, 'name' => mb_convert_kana($a[0], 'asv', 'UTF-8')];
						// リモコン番号があれば取得
						if (isset($services[$onid.'-'.$tsid.'-'.$sid])) {
							$ch[$chkey]['remocon'] = $services[$onid.'-'.$tsid.'-'.$sid]['remote_control_key_id'];
						}
					}
				}
			}
		}
		ksort($ch);

		return $ch;
	}

	// BOM に基づいて文字コードを変換する
	function detectBomAndConvertEncoding(string $s, string $enc): ?string {

		if (strncmp($s, "\xef\xbb\xbf", 3) === 0) {
			$s = substr($s, 3);
		} elseif (strncmp($s, "\xff\xfe", 2) === 0) {
			$s = substr($s, 2);
			if (strlen($s) > 0) $s = @iconv('UTF-16LE', 'UTF-8', $s);
		} else {
			if (strlen($s) > 0) $s = @iconv($enc, 'UTF-8', $s);
		}
		return $s === false ? null : $s;
	}
