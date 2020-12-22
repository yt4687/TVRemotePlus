<?php

require_once ('classloader.php');

class JikkyoController {
    

    /**
     * コンストラクタ
     */
    public function __construct() {

        require ('module.php');
        require ('require.php');

        // ストリーム番号を取得
        $stream = getStreamNumber($_SERVER['REQUEST_URI']);

        // 設定ファイル読み込み
        $settings = json_decode(file_get_contents($inifile), true);

        // ストリームが存在する
        if (isset($settings[$stream])) {

            // ストリーム状態が ON Air & チャンネルが 0 でない
            if ($settings[$stream]['state'] === 'ONAir' and intval($settings[$stream]['channel']) !== 0){ 
    
                // BonDriver とチャンネルを取得
                // 実際はチャンネルしか使わないのでこんなにいらない（👈技術的負債）
		list($BonDriver_dll, $BonDriver_dll_T, $BonDriver_dll_S, $BonDriver_dll_SPHD, // BonDriver
			$ch, $ch_T, $ch_S, $ch_CS, $ch_SPHD, $ch_SPSD, // チャンネル番号
			$sid, $sid_T, $sid_S, $sid_CS, $sid_SPHD, $sid_SPSD, // SID
			$onid, $onid_T, $onid_S, $onid_CS, $onid_SPHD, $onid_SPSD, // ONID(NID)
<<<<<<< Updated upstream
			$tsid, $tsid_T, $tsid_S, $tsid_CS, $tsid_SPHD, $tsid_SPSD) // TSID
			= initBonChannel($BonDriver_dir);
=======
			$tsid, $tsid_T, $tsid_S, $tsid_CS, $tsid_SPHD, $tsid_SPSD) // TSID // TSID
                    = initBonChannel($BonDriver_dir);
>>>>>>> Stashed changes
    
                // モデルを初期化
                $instance = new Jikkyo($nicologin_mail, $nicologin_password);
    
                // 実況 ID を取得
                if (isset($ch[$settings[$stream]['channel']])){
                    $nicojikkyo_id = $instance->getNicoJikkyoID($ch[$settings[$stream]['channel']]);
                } else if ($ch[intval($settings[$stream]['channel']).'_1']){
                    $nicojikkyo_id = $instance->getNicoJikkyoID($ch[intval($settings[$stream]['channel']).'_1']);
                } else {
                    $nicojikkyo_id = -2;
                }
    
                // 実況 ID が 1 以上であれば続行
                // 実況 ID が 1 以下は実況チャンネルが存在しない
                if ($nicojikkyo_id > 0) {
    
                    // 実況 ID からニコニコチャンネル ID を取得する
                    $nicochannel_id = $instance->getNicoChannelID($nicojikkyo_id);
    
                    // ニコニコチャンネル ID が存在する（=実況 ID がニコニコチャンネル上に存在する）
                    if ($nicochannel_id !== null) {
    
                        // ニコニコチャンネル ID から、現在放送中のニコ生の放送 ID を取得する
                        $nicolive_id = $instance->getNicoLiveID($nicochannel_id);
    
                        // 放送 ID が null でない（=現在放送中）
                        if ($nicolive_id !== null) {
    
                            // ニコ生のセッション情報を取得
                            $nicolive_session = $instance->getNicoliveSession($nicolive_id);

                            // WebSocket の URL が空
                            if (empty($nicolive_session['watchsession_url'])) {
                                $message = '視聴セッションを取得できませんでした。';
                            }
                        } else {
                            $message = '現在放送中のニコニコ実況がありません。';
                        }
                    } else {
                        $message = 'このチャンネルのニコニコ実況は廃止されました。';
                    }
                } else {
                    $message = 'このチャンネルのニコニコ実況はありません。';
                }

            // ファイル再生
            } else if ($settings[$stream]['state'] == 'File') {

                // 録画の開始/終了時刻のタイムスタンプ
                $start_timestamp = $settings[$stream]['start_timestamp'];
                $end_timestamp = $settings[$stream]['end_timestamp'];
    
                // モデルを初期化
                $instance = new Jikkyo($nicologin_mail, $nicologin_password);

                // 実況 ID を取得
                $nicojikkyo_id = $instance->getNicoJikkyoID($settings[$stream]['filechannel']);

                // 実況 ID が 1 以上であれば続行
                // 実況 ID が 1 以下は実況チャンネルが存在しない
                if ($nicojikkyo_id > 0) {

                    // 過去ログを（ DPlayer 互換フォーマットで）取得
                    // JavaScript 側で変換することもできるけどコメントが大量だと重くなりそうで
                    list($kakolog, $kakolog_url) = $instance->getNicoJikkyoKakolog($nicojikkyo_id, $start_timestamp, $end_timestamp);

                    // 過去ログが配列でない（＝エラーメッセージが入っている）
                    if (!is_array($kakolog)) {
                        $message = $kakolog;
                    }

                } else {
                    $message = 'このチャンネルのニコニコ実況はありません。';
                }

            } else {
                $message = "Stream {$stream} は Offline です。";
            }

        } else {
            $message = "Stream {$stream} は存在しません。";
        }


        // ライブ配信
        // ニコ生のセッション情報を取得できているか
        if ($settings[$stream]['state'] == 'ONAir' && isset($nicolive_session) && !empty($nicolive_session['watchsession_url'])) {

            // 出力
            $output = [
                'api' => 'jikkyo',
                'result' => 'success',
                'session' => $nicolive_session,
            ];

        // ファイル再生
        // 過去ログが取得できていれば
        } else if ($settings[$stream]['state'] == 'File' && is_array($kakolog)) {

            // 出力
            $output = [
                'api' => 'jikkyo',
                'result' => 'success',
                'kakolog_url' => $kakolog_url,
                'kakolog' => $kakolog,
            ];

        // 何らかの要因でセッションを取得できなかった
        // エラーメッセージがあればそれを出力
        } else {

            // 出力
            $output = [
                'api' => 'jikkyo',
                'result' => 'error',
                'message' => (isset($message) ? $message : '不明なエラーが発生しました。'),
            ];

        }

        // JSON を表示
        header('content-type: application/json; charset=utf-8');
        echo json_encode($output, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
}
