<?php

// EpgTimerSrv の CtrlCmd インタフェースと通信する (EDCB/EpgTimer の CtrlCmd(Def).cs を移植したもの)
// ・利用可能なコマンドはもっとあるが使いそうなものだけ
// ・sendView* 系コマンドは EpgDataCap_Bon 等との通信用。接続先パイプは "View_Ctrl_BonNoWaitPipe_{プロセス ID}"

class CtrlCmdUtil
{
	// 名前付きパイプモードにする
	public function setPipeSetting(string $name)
	{
		$this->pipeName = $name;
		$this->uri = null;
		$this->timeOutFlag = false;
	}

	// 接続先パイプが存在するか調べる
	public function pipeExists(): bool
	{
		// パイプが開かれていると stat() は失敗するので、file_exists() だと精度が劣る
		$dh = @opendir('\\\\.\\pipe\\');
		if ($dh) {
			while (($file = readdir($dh)) !== false) {
				if (strcasecmp($file, $this->pipeName) === 0) {
					closedir($dh);
					return true;
				}
			}
			closedir($dh);
			if (!file_exists('\\\\.\\pipe\\' . $this->pipeName)) return false;
			// 矛盾発生
		}
		// 仕方ないので少し観察
		for ($i = 0; $i < 10; ++$i) {
			clearstatcache();
			if (file_exists('\\\\.\\pipe\\' . $this->pipeName)) return true;
			usleep(1000);
		}
		return false;
	}

	// TCP/IP モードにする
	public function setNWSetting(string $addr)
	{
		$this->uri = 'tcp://' . $addr;
		$this->timeOutFlag = false;
	}

	// 接続処理時のタイムアウト設定
	public function setConnectTimeOutUsec($timeOut)
	{
		$this->connectTimeOutUsec = (int)$timeOut;
		$this->timeOutFlag = false;
	}

	// BonDriver の切り替え
	public function sendViewSetBonDriver(string $name): bool
	{
		$buf = '';
		self::writeInt($buf, self::CMD_VIEW_APP_SET_BONDRIVER);
		self::writeInt($buf, 0);
		self::writeString($buf, $name);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		return $ret === self::CMD_SUCCESS;
	}

	// 使用中の BonDriver のファイル名を取得
	public function sendViewGetBonDriver(): ?string
	{
		$buf = '';
		self::writeInt($buf, self::CMD_VIEW_APP_GET_BONDRIVER);
		self::writeInt($buf, 0);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		if ($ret === self::CMD_SUCCESS) {
			if (self::readString($v, $buf, $pos, $size)) {
				return $v;
			}
		}
		return null;
	}

	// チャンネル切り替え
	public function sendViewSetCh(array $info): bool
	{
		$buf = '';
		self::writeInt($buf, self::CMD_VIEW_APP_SET_CH);
		self::writeInt($buf, 0);
		self::writeSetChInfo($buf, $info);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		return $ret === self::CMD_SUCCESS;
	}

	// アプリケーションの終了
	public function sendViewAppClose(): bool
	{
		$buf = '';
		self::writeInt($buf, self::CMD_VIEW_APP_CLOSE);
		self::writeInt($buf, 0);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		return $ret === self::CMD_SUCCESS;
	}

	// サービス一覧を取得する
	public function sendEnumService(): ?array
	{
		$buf = '';
		self::writeInt($buf, self::CMD_EPG_SRV_ENUM_SERVICE);
		self::writeInt($buf, 0);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		if ($ret === self::CMD_SUCCESS) {
			if (self::readVector('readServiceInfo', $v, $buf, $pos, $size)) {
				return $v;
			}
		}
		return null;
	}

	// 指定イベントの番組情報を取得する
	public function sendGetPgInfo($onid, $tsid, $sid, $eid): ?array
	{
		$buf = '';
		self::writeInt($buf, self::CMD_EPG_SRV_GET_PG_INFO);
		self::writeInt($buf, 0);
		self::writeUshort($buf, $eid);
		self::writeUshort($buf, $sid);
		self::writeUshort($buf, $tsid);
		self::writeUshort($buf, $onid);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		if ($ret === self::CMD_SUCCESS) {
			if (self::readEventInfo($v, $buf, $pos, $size)) {
				return $v;
			}
		}
		return null;
	}

	// 番組情報の最小開始時間と最大開始時間を取得する
	public function sendGetPgInfoMinmax(array $list): ?array
	{
		$buf = '';
		self::writeInt($buf, self::CMD_EPG_SRV_GET_PG_INFO_MINMAX);
		self::writeInt($buf, 0);
		self::writeVector('writeLong', $buf, $list);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		if ($ret === self::CMD_SUCCESS) {
			if (self::readVector('readLong', $v, $buf, $pos, $size)) {
				return $v;
			}
		}
		return null;
	}

	// サービス指定と時間指定で番組情報一覧を取得する
	public function sendEnumPgInfoEx(array $list): ?array
	{
		$buf = '';
		self::writeInt($buf, self::CMD_EPG_SRV_ENUM_PG_INFO_EX);
		self::writeInt($buf, 0);
		self::writeVector('writeLong', $buf, $list);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		if ($ret === self::CMD_SUCCESS) {
			if (self::readVector('readServiceEventInfo', $v, $buf, $pos, $size)) {
				return $v;
			}
		}
		return null;
	}

	// サービス指定と時間指定で過去番組情報一覧を取得する
	public function sendEnumPgArc(array $list): ?array
	{
		$buf = '';
		self::writeInt($buf, self::CMD_EPG_SRV_ENUM_PG_ARC);
		self::writeInt($buf, 0);
		self::writeVector('writeLong', $buf, $list);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		if ($ret === self::CMD_SUCCESS) {
			if (self::readVector('readServiceEventInfo', $v, $buf, $pos, $size)) {
				return $v;
			}
		}
		return null;
	}

	// 指定ファイルを転送する
	public function sendFileCopy(string $name): ?string
	{
		$buf = '';
		self::writeInt($buf, self::CMD_EPG_SRV_FILE_COPY);
		self::writeInt($buf, 0);
		self::writeString($buf, $name);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		if ($ret === self::CMD_SUCCESS) {
			return substr($buf, 8, $size - 8);
		}
		return null;
	}

	// NetworkTV モードの View アプリのチャンネルを切り替え、または起動の確認 (ID 指定)
	public function sendNwTVIDSetCh(array $info): ?int
	{
		$buf = '';
		self::writeInt($buf, self::CMD_EPG_SRV_NWTV_ID_SET_CH);
		self::writeInt($buf, 0);
		self::writeSetChInfo($buf, $info);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		if ($ret === self::CMD_SUCCESS) {
			if (self::readInt($processID, $buf, $pos, $size)) {
				return $processID;
			}
		}
		return null;
	}

	// NetworkTV モードで起動中の View アプリを終了 (ID 指定)
	public function sendNwTVIDClose(int $id): bool
	{
		$buf = '';
		self::writeInt($buf, self::CMD_EPG_SRV_NWTV_ID_CLOSE);
		self::writeInt($buf, 0);
		self::writeInt($buf, $id);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		return $ret === self::CMD_SUCCESS;
	}

	// 録画済み情報一覧取得 (programInfo と errInfo を除く)
	public function sendEnumRecInfoBasic2(): ?array
	{
		$buf = '';
		self::writeInt($buf, self::CMD_EPG_SRV_ENUM_RECINFO_BASIC2);
		self::writeInt($buf, 0);
		self::writeUshort($buf, self::CMD_VER);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		if ($ret === self::CMD_SUCCESS) {
			if (self::readUshort($ver, $buf, $pos, $size) && $ver >= self::CMD_VER &&
			    self::readVector('readRecFileInfo', $v, $buf, $pos, $size)) {
				return $v;
			}
		}
		return null;
	}

	// 録画済み情報取得
	public function sendGetRecInfo2($id): ?array
	{
		$buf = '';
		self::writeInt($buf, self::CMD_EPG_SRV_GET_RECINFO2);
		self::writeInt($buf, 0);
		self::writeUshort($buf, self::CMD_VER);
		self::writeInt($buf, $id);
		self::writeIntInplace($buf, 4, strlen($buf) - 8);
		[$ret, $buf, $pos, $size] = $this->sendAndReceive($buf);
		if ($ret === self::CMD_SUCCESS) {
			if (self::readUshort($ver, $buf, $pos, $size) && $ver >= self::CMD_VER &&
			    self::readRecFileInfo($v, $buf, $pos, $size)) {
				return $v;
			}
		}
		return null;
	}

	// UNIX 時間を UTC+9 の FILETIME に変換する
	public static function unixTimeToJstFileTime($t)
	{
		return ($t + 32400 + 11644473600) * 10000000;
	}

	// UTC+9 の FILETIME を UNIX 時間に変換する
	public static function jstFileTimeToUnixTime($ft): int
	{
		return (int)($ft / 10000000 - 11644473600 - 32400);
	}

	// EDCB/EpgTimer の CtrlCmd.cs より
	private const CMD_SUCCESS = 1;
	private const CMD_VER = 5;
	private const CMD_VIEW_APP_SET_BONDRIVER = 201;
	private const CMD_VIEW_APP_GET_BONDRIVER = 202;
	private const CMD_VIEW_APP_SET_CH = 205;
	private const CMD_VIEW_APP_CLOSE = 208;
	private const CMD_EPG_SRV_ENUM_SERVICE = 1021;
	private const CMD_EPG_SRV_GET_PG_INFO = 1023;
	private const CMD_EPG_SRV_GET_PG_INFO_MINMAX = 1028;
	private const CMD_EPG_SRV_ENUM_PG_INFO_EX = 1029;
	private const CMD_EPG_SRV_ENUM_PG_ARC = 1030;
	private const CMD_EPG_SRV_FILE_COPY = 1060;
	private const CMD_EPG_SRV_NWTV_ID_SET_CH = 1073;
	private const CMD_EPG_SRV_NWTV_ID_CLOSE = 1074;
	private const CMD_EPG_SRV_ENUM_RECINFO_BASIC2 = 2020;
	private const CMD_EPG_SRV_GET_RECINFO2 = 2024;

	private $connectTimeOutUsec = 15000000;
	private $pipeName = 'EpgTimerSrvNoWaitPipe';
	private $uri = null;
	private $timeOutFlag = false;

	// バイナリデータを送信→受信する
	private function sendAndReceive($buf)
	{
		$failed = [0, '', 0, 0];
		$t = microtime(true);
		$checked = false;

		// シリアライザの都合により BigEndian は未対応 (対応は難しくないが、市販 PC から BE は絶滅寸前で動機がない)
		if ($this->timeOutFlag || pack('l', 1)[0] !== "\1") return $failed;

		do {
			$fp = isset($this->uri) ? @stream_socket_client($this->uri, $errno, $errstr, $this->connectTimeOutUsec / 1000000) :
			                          @fopen('\\\\.\\pipe\\' . $this->pipeName, 'r+');
			if ($fp) {
				// すべて送信
				for ($written = 0; $written < strlen($buf); $written += $n) {
					if (($n = fwrite($fp, substr($buf, $written))) === false) break;
				}
				if ($written === strlen($buf) && fflush($fp)) {
					// 終わりまで受信
					$buf = stream_get_contents($fp);
					fclose($fp);
					if ($buf !== false && strlen($buf) >= 8) {
						$pos = 0;
						self::readInt($ret, $buf, $pos, 8);
						self::readInt($size, $buf, $pos, 8);
						$size += 8;
						if ($size <= strlen($buf)) {
							return [$ret, $buf, $pos, $size];
						}
					}
					return $failed;
				}
				fclose($fp);
				return $failed;
			}
			// TCP/IP モードでは接続先に待ちキューがあるのでポーリング不要
			if (isset($this->uri)) break;

			if (!$checked) {
				// 接続先が存在しないならタイムアウトを待たずに抜ける
				if (!$this->pipeExists()) return $failed;
				$checked = true;
			}
			usleep(1000);
		} while (abs(microtime(true) - $t) < $this->connectTimeOutUsec);

		// 次回から速やかに失敗させる
		$this->timeOutFlag = true;
		return $failed;
	}

	// 符号なし1バイトを書き込む
	private static function writeByte(&$buf, $v)
	{
		$buf .= pack('C', $v);
	}

	// 符号なし2バイトを書き込む
	private static function writeUshort(&$buf, $v)
	{
		$buf .= pack('v', $v);
	}

	// 符号つき4バイトを書き込む
	private static function writeInt(&$buf, $v)
	{
		$buf .= pack('l', $v);
	}

	// 符号つき8バイトを書き込む (演算精度等により 53bit を超える整数には誤差が乗るかも)
	private static function writeLong(&$buf, $v)
	{
		// 64bit 環境に限るなら pack('q') でもよい
		$x = 0;
		if ($v < 0) {
			$v = -($v + 1);
			$x = 0xFFFF;
		}
		$buf .= pack('v4', 0xFFFF & ($x ^ fmod($v, 0x10000)),
		                   0xFFFF & ($x ^ fmod($v / 0x10000, 0x10000)),
		                   0xFFFF & ($x ^ fmod($v / 0x100000000, 0x10000)),
		                   0xFFFF & ($x ^ fmod($v / 0x1000000000000, 0x10000)));
	}

	// 符号つき4バイトを $pos に上書きする
	private static function writeIntInplace(&$buf, $pos, $v)
	{
		$v = pack('l', $v);
		$buf[$pos] = $v[0];
		$buf[$pos + 1] = $v[1];
		$buf[$pos + 2] = $v[2];
		$buf[$pos + 3] = $v[3];
	}

	// 文字列を書き込む
	private static function writeString(&$buf, $v)
	{
		$v = iconv('UTF-8', 'UTF-16LE', $v);
		self::writeInt($buf, 6 + strlen($v));
		$buf .= $v;
		self::writeUshort($buf, 0);
	}

	// 配列を書き込む
	private static function writeVector($writeFunc, &$buf, $v)
	{
		$pos = strlen($buf);
		self::writeInt($buf, 0);
		$vc = count($v);
		self::writeInt($buf, $vc);
		for ($i = 0; $i < $vc; ++$i) {
			self::$writeFunc($buf, $v[$i]);
		}
		self::writeIntInplace($buf, $pos, strlen($buf) - $pos);
	}

	// 以下、各構造体のライター
	// 各キーの意味は CtrlCmdDef.cs のクラス定義を参照のこと

	private static function writeSetChInfo(&$buf, $v)
	{
		$pos = strlen($buf);
		self::writeInt($buf, 0);
		self::writeInt($buf, $v['useSID']);
		self::writeUshort($buf, $v['onid']);
		self::writeUshort($buf, $v['tsid']);
		self::writeUshort($buf, $v['sid']);
		self::writeInt($buf, $v['useBonCh']);
		self::writeInt($buf, $v['space']);
		self::writeInt($buf, $v['ch']);
		self::writeIntInplace($buf, $pos, strlen($buf) - $pos);
	}

	// 符号なし1バイトを読み込む
	private static function readByte(&$v, $buf, &$pos, $size)
	{
		if ($size - $pos < 1) return false;
		$v = unpack('C', $buf, $pos)[1];
		++$pos;
		return true;
	}

	// 符号なし2バイトを読み込む
	private static function readUshort(&$v, $buf, &$pos, $size)
	{
		if ($size - $pos < 2) return false;
		$v = unpack('v', $buf, $pos)[1];
		$pos += 2;
		return true;
	}

	// 符号つき4バイトを読み込む
	private static function readInt(&$v, $buf, &$pos, $size)
	{
		if ($size - $pos < 4) return false;
		$v = unpack('l', $buf, $pos)[1];
		$pos += 4;
		return true;
	}

	// 符号つき8バイトを読み込む (演算精度等により 53bit を超える整数には誤差が乗るかも)
	private static function readLong(&$v, $buf, &$pos, $size)
	{
		if ($size - $pos < 8) return false;
		// 64bit 環境に限るなら unpack('q') でもよい
		$a = unpack('v4', $buf, $pos);
		if ($a[4] & 0x8000) {
			$v = -((0xFFFF & ~$a[1]) + 0x10000 * ((0xFFFF & ~$a[2]) + 0x10000 * ((0xFFFF & ~$a[3]) + 0x10000 * (0xFFFF & ~$a[4])))) - 1;
		} else {
			$v = $a[1] + 0x10000 * ($a[2] + 0x10000 * ($a[3] + 0x10000 * $a[4]));
		}
		$pos += 8;
		return true;
	}

	// UTC+9 の SYSTEMTIME を UNIX 時間として読み込む
	private static function readSystemTime(&$v, $buf, &$pos, $size)
	{
		if ($size - $pos < 16) return false;
		$x = unpack('v7', $buf, $pos);
		$v = gmmktime($x[5], $x[6], $x[7], $x[2], $x[4], $x[1]) - 32400;
		$pos += 16;
		return true;
	}

	// 文字列を読み込む
	private static function readString(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 6 || $size - $pos < $vs - 4) return false;
		$v = iconv('UTF-16LE', 'UTF-8', substr($buf, $pos, $vs - 6));
		$pos += $vs - 4;
		return true;
	}

	// 配列を読み込む
	private static function readVector($readFunc, &$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || !self::readInt($vc, $buf, $pos, $size) ||
		    $vs < 8 || $size - $pos < $vs - 8) return false;
		$size = $pos + $vs - 8;
		$v = [];
		for ($i = 0; $i < $vc; ++$i) {
			if (!self::$readFunc($v[$i], $buf, $pos, $size)) {
				return false;
			}
		}
		$pos = $size;
		return true;
	}

	// 以下、各構造体のリーダー
	// 各キーの意味は CtrlCmdDef.cs のクラス定義を参照のこと

	private static function readRecFileInfo(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readInt($v['id'], $buf, $pos, $size) ||
		    !self::readString($v['recFilePath'], $buf, $pos, $size) ||
		    !self::readString($v['title'], $buf, $pos, $size) ||
		    !self::readSystemTime($v['startTime'], $buf, $pos, $size) ||
		    !self::readInt($v['durationSecond'], $buf, $pos, $size) ||
		    !self::readString($v['serviceName'], $buf, $pos, $size) ||
		    !self::readUshort($v['onid'], $buf, $pos, $size) ||
		    !self::readUshort($v['tsid'], $buf, $pos, $size) ||
		    !self::readUshort($v['sid'], $buf, $pos, $size) ||
		    !self::readUshort($v['eid'], $buf, $pos, $size) ||
		    !self::readLong($v['drops'], $buf, $pos, $size) ||
		    !self::readLong($v['scrambles'], $buf, $pos, $size) ||
		    !self::readInt($v['recStatus'], $buf, $pos, $size) ||
		    !self::readSystemTime($v['startTimeEpg'], $buf, $pos, $size) ||
		    !self::readString($v['comment'], $buf, $pos, $size) ||
		    !self::readString($v['programInfo'], $buf, $pos, $size) ||
		    !self::readString($v['errInfo'], $buf, $pos, $size) ||
		    !self::readByte($v['protectFlag'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readServiceEventInfo(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readServiceInfo($v['serviceInfo'], $buf, $pos, $size) ||
		    !self::readVector('readEventInfo', $v['eventList'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readServiceInfo(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readUshort($v['onid'], $buf, $pos, $size) ||
		    !self::readUshort($v['tsid'], $buf, $pos, $size) ||
		    !self::readUshort($v['sid'], $buf, $pos, $size) ||
		    !self::readByte($v['service_type'], $buf, $pos, $size) ||
		    !self::readByte($v['partialReceptionFlag'], $buf, $pos, $size) ||
		    !self::readString($v['service_provider_name'], $buf, $pos, $size) ||
		    !self::readString($v['service_name'], $buf, $pos, $size) ||
		    !self::readString($v['network_name'], $buf, $pos, $size) ||
		    !self::readString($v['ts_name'], $buf, $pos, $size) ||
		    !self::readByte($v['remote_control_key_id'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readEventInfo(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readUshort($v['onid'], $buf, $pos, $size) ||
		    !self::readUshort($v['tsid'], $buf, $pos, $size) ||
		    !self::readUshort($v['sid'], $buf, $pos, $size) ||
		    !self::readUshort($v['eid'], $buf, $pos, $size) ||
		    !self::readByte($startTimeFlag, $buf, $pos, $size) ||
		    !self::readSystemTime($v['start_time'], $buf, $pos, $size) ||
		    !self::readByte($durationFlag, $buf, $pos, $size) ||
		    !self::readInt($v['durationSec'], $buf, $pos, $size)) return false;

		if ($startTimeFlag === 0) unset($v['start_time']);
		if ($durationFlag === 0) unset($v['durationSec']);

		if (!self::readInt($n, $buf, $pos, $size)) return false;
		if ($n !== 4) {
			$pos -= 4;
			if (!self::readShortEventInfo($v['shortInfo'], $buf, $pos, $size)) return false;
		}
		if (!self::readInt($n, $buf, $pos, $size)) return false;
		if ($n !== 4) {
			$pos -= 4;
			if (!self::readExtendedEventInfo($v['extInfo'], $buf, $pos, $size)) return false;
		}
		if (!self::readInt($n, $buf, $pos, $size)) return false;
		if ($n !== 4) {
			$pos -= 4;
			if (!self::readContentInfo($v['contentInfo'], $buf, $pos, $size)) return false;
		}
		if (!self::readInt($n, $buf, $pos, $size)) return false;
		if ($n !== 4) {
			$pos -= 4;
			if (!self::readComponentInfo($v['componentInfo'], $buf, $pos, $size)) return false;
		}
		if (!self::readInt($n, $buf, $pos, $size)) return false;
		if ($n !== 4) {
			$pos -= 4;
			if (!self::readAudioComponentInfo($v['audioInfo'], $buf, $pos, $size)) return false;
		}
		if (!self::readInt($n, $buf, $pos, $size)) return false;
		if ($n !== 4) {
			$pos -= 4;
			if (!self::readEventGroupInfo($v['eventGroupInfo'], $buf, $pos, $size)) return false;
		}
		if (!self::readInt($n, $buf, $pos, $size)) return false;
		if ($n !== 4) {
			$pos -= 4;
			if (!self::readEventGroupInfo($v['eventRelayInfo'], $buf, $pos, $size)) return false;
		}

		if (!self::readByte($v['freeCAFlag'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readShortEventInfo(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readString($v['event_name'], $buf, $pos, $size) ||
		    !self::readString($v['text_char'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readExtendedEventInfo(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readString($v['text_char'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readContentInfo(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readVector('readContentData', $v['nibbleList'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readContentData(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readUshort($v['content_nibble'], $buf, $pos, $size) ||
		    !self::readUshort($v['user_nibble'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readComponentInfo(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readByte($v['stream_content'], $buf, $pos, $size) ||
		    !self::readByte($v['component_type'], $buf, $pos, $size) ||
		    !self::readByte($v['component_tag'], $buf, $pos, $size) ||
		    !self::readString($v['text_char'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readAudioComponentInfo(&$v, $buf, &$pos, $size): bool
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readVector('readAudioComponentInfoData', $v['componentList'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readAudioComponentInfoData(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readByte($v['stream_content'], $buf, $pos, $size) ||
		    !self::readByte($v['component_type'], $buf, $pos, $size) ||
		    !self::readByte($v['component_tag'], $buf, $pos, $size) ||
		    !self::readByte($v['stream_type'], $buf, $pos, $size) ||
		    !self::readByte($v['simulcast_group_tag'], $buf, $pos, $size) ||
		    !self::readByte($v['ES_multi_lingual_flag'], $buf, $pos, $size) ||
		    !self::readByte($v['main_component_flag'], $buf, $pos, $size) ||
		    !self::readByte($v['quality_indicator'], $buf, $pos, $size) ||
		    !self::readByte($v['sampling_rate'], $buf, $pos, $size) ||
		    !self::readString($v['text_char'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readEventGroupInfo(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readByte($v['group_type'], $buf, $pos, $size) ||
		    !self::readVector('readEventData', $v['eventDataList'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}

	private static function readEventData(&$v, $buf, &$pos, $size)
	{
		if (!self::readInt($vs, $buf, $pos, $size) || $vs < 4 || $size - $pos < $vs - 4) return false;
		$size = $pos + $vs - 4;
		$v = [];
		if (!self::readUshort($v['onid'], $buf, $pos, $size) ||
		    !self::readUshort($v['tsid'], $buf, $pos, $size) ||
		    !self::readUshort($v['sid'], $buf, $pos, $size) ||
		    !self::readUshort($v['eid'], $buf, $pos, $size)) return false;
		$pos = $size;
		return true;
	}
}
