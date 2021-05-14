<?php

require_once ('classloader.php');

class JikkyoController {


    /**
     * コンストラクタ
     */
    public function __construct($live_id = null) {

        require ('module.php');
        require ('require.php');

        // ストリーム番号を取得
        $stream = getStreamNumber($_SERVER['REQUEST_URI']);

        // 設定ファイル読み込み
        $settings = json_decode(file_get_contents_lock_sh($inifile), true);

        // ストリームが存在する
        if (isset($settings[$stream])) {

            // ストリーム状態が ON Air & チャンネルが 0 でない
            if ($settings[$stream]['state'] === 'ONAir' and intval($settings[$stream]['channel']) !== 0){

                // モデルを初期化
                $instance = new Jikkyo($nicologin_mail, $nicologin_password);

                // 放送 ID が指定された場合はそれを使う
                if (isset($live_id)) {

                    // ニコニコチャンネル/コミュニティ ID として設定　lv から始まる ID が入る場合もあるが、
                    // getNicoliveSession() はいずれの ID も処理できるので問題はない
                    $nicochannel_id = $live_id;

                // ストリーム番号から現在放送中のチャンネルの実況 ID を使う
                } else {

                    // BonDriver とチャンネルを取得
                    // 実際はチャンネルしか使わないのでこんなにいらない（👈技術的負債）
                    list($BonDriver_dll, $BonDriver_dll_T, $BonDriver_dll_S, $BonDriver_dll_SPHD, // BonDriver
                        $ch, $ch_T, $ch_S, $ch_CS, $ch_SPHD, $ch_SPSD, // チャンネル番号
                        $sid, $sid_T, $sid_S, $sid_CS, $sid_SPHD, $sid_SPSD, // SID
                        $onid, $onid_T, $onid_S, $onid_CS, $onid_SPHD, $onid_SPSD, // ONID(NID)
                        $tsid, $tsid_T, $tsid_S, $tsid_CS, $tsid_SPHD, $tsid_SPSD) // TSID
                        = initBonChannel($BonDriver_dir);

                    // 実況 ID を取得
                    if (isset($ch[$settings[$stream]['channel']])){
                        $nicojikkyo_id = $instance->getNicoJikkyoID($ch[$settings[$stream]['channel']]);
                    } else if ($ch[intval($settings[$stream]['channel']).'_1']){
                        $nicojikkyo_id = $instance->getNicoJikkyoID($ch[intval($settings[$stream]['channel']).'_1']);
                    } else {
                        $nicojikkyo_id = null;
                    }
                }

                // ニコニコチャンネル/コミュニティ ID が定義済み or 実況 ID が存在する
                if (isset($nicochannel_id) or $nicojikkyo_id !== null) {

                    // ニコニコチャンネル/コミュニティ ID が定義されていない場合のみ、
                    // 実況 ID からニコニコチャンネル/コミュニティ ID を取得する
                    if (!isset($nicochannel_id)) {
                        $nicochannel_id = $instance->getNicoChannelID($nicojikkyo_id);
                    }

                    // ニコニコチャンネル/コミュニティ ID が存在する（＝実況 ID がニコニコチャンネル上に存在する）
                    if ($nicochannel_id !== null) {

                        // ニコ生のセッション情報を取得
                        $nicolive_session = $instance->getNicoliveSession($nicochannel_id);

                        // 現在放送中でない（タイムシフト or 予約中）
                        if ($nicolive_session === null) {

                            $message = '現在放送中のニコニコ実況がありません。';

                        // HTTP エラー
                        } else if (isset($nicolive_session['error'])) {

                            $message = $nicolive_session['error'];

                        // WebSocket の URL が空
                        } else if (empty($nicolive_session['watchsession_url'])) {

                            $message = '視聴セッションを取得できませんでした。';
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

                // 実況 ID が存在する
                if ($nicojikkyo_id !== null) {

                    // 過去ログと過去ログの URL を（ DPlayer 互換フォーマットで）取得
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
        if (isset($settings[$stream]) and $settings[$stream]['state'] == 'ONAir' and isset($nicolive_session) and !empty($nicolive_session['watchsession_url'])) {

            // 出力
            $output = [
                'api' => 'jikkyo',
                'type' => 'onair',
                'result' => 'success',
                'session' => $nicolive_session,
            ];

        // ファイル再生
        // 過去ログが取得できていれば
        } else if (isset($settings[$stream]) and $settings[$stream]['state'] == 'File' and isset($kakolog) and is_array($kakolog)) {

            // 出力
            $output = [
                'api' => 'jikkyo',
                'type' => 'file',
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
                'type' => 'error',
                'result' => 'error',
                'message' => (isset($message) ? $message : '不明なエラーが発生しました。'),
            ];

        }

        // JSON を表示
        header('content-type: application/json; charset=utf-8');
        echo json_encode($output, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
}
