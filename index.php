<?php

require_once  __DIR__ .'/vendor/autoload.php';

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\Event\MessageEvent;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;

const GNAVI_URI        = 'グルナビAPIのURI';
const GNAVI_ACCESS_KEY = 'グルナビAPIのアクセスキー';
const GNAVI_HIT_PER_PAGE = '5';
const GNAVI_CATEGORY_L = 'カテゴリ大';

const GOOGLE_URLSHORT_URI = 'GoogleAPIのURI';
const GOOGLE_URLSHORT_KEY = 'GoogleAPIのアクセスキー';

// トークンとシークレットキーを取得してインスタンス化
$httpClient = new CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
$bot        = new LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);

$signature = $_SERVER["HTTP_" . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];
try {
	// LINEからPOSTされたデータを取得、不正パラメータをエラーで返す
	$events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
} catch(\LINE\LINEBot\Exception\InvalidSignatureException $e) {
	error_log("parseEventRequest failed. InvalidSignatureException => ".var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownEventTypeException $e) {
	error_log("parseEventRequest failed. UnknownEventTypeException => ".var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownMessageTypeException $e) {
	error_log("parseEventRequest failed. UnknownMessageTypeException => ".var_export($e, true));
} catch(\LINE\LINEBot\Exception\InvalidEventRequestException $e) {
	error_log("parseEventRequest failed. InvalidEventRequestException => ".var_export($e, true));
}

// メッセージイベント判定
foreach ($events as $event) {
	if (!($event instanceof MessageEvent)) {
		error_log('Non message event has come');
		continue;
	}

	if (!($event instanceof MessageEvent\LocationMessage)) {
 		$bot->replyMessage($event->getReplyToken(),
 				(new MultiMessageBuilder())
 				->add(new TextMessageBuilder('位置情報が欲しいんじゃー'))
 				);
 		continue;
	}

	// グルナビのAPIをたたく
	$response = curl_execute('GET', makeGnaviURI($event));

 	// XMLパースし、Arrayで展開
 	$response = simplexml_load_string($response);
 	$response = json_decode(json_encode($response),true);

 	if(array_key_exists('error', $response)){
 		if($response['error']['code'] != 600){ // 0件以外のエラー
 			error_log("GNAVI API EXCEPTION status:".$response['error']['code']. " message:". $response['error']['message']);
 			$bot->replyMessage($event->getReplyToken(),(new MultiMessageBuilder())
 					->add(new TextMessageBuilder('グルナビAPIのエラーにより、お店が検索できませんでした')));
 			continue;
 		}
 	}

 	// グルナビAPIのレスポンスからメッセージを作成
 	$message = new MultiMessageBuilder();
 	$ramenList = $response['rest'];

 	if(count($ramenList) > 0){
 		foreach($ramenList as $ramen){
 			$ramenData = makeRramenData($ramen);
 			$message->add(new TextMessageBuilder($ramenData));
 		}
 	} else {
 		$message->add(new TextMessageBuilder('麺系のお店が見つかりませんでした。'));
 	}

 	// メッセージ送信
 	$bot->replyMessage($event->getReplyToken(),$message);

}

/**
 * cURL実行
 * @param string $method
 * @param string $uri
 * @param array $data
 * @return mixed
 */
function curl_execute($method, $uri, $postData=null)
{
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $uri);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 証明書の検証を行わない
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 証明書の検証を行わない
	if($method == 'POST'){
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=UTF-8'));
		//curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));
	}
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);  // curl_execの結果を文字列で返す

	try {
		$response = curl_exec($curl);
	} catch(\Exception $e){
		error_log("APIRequest failed. => ".var_export($e, true));
	}
	curl_close($curl);

	return $response;
}

/**
 * ラーメン情報を作成する。
 * @param  Array  $ramen
 * @return string $ret
 */
function makeRramenData($ramen)
{
	$ret = '';
	$ret .= '■名前'  ."\n";
	$ret .= (!empty($ramen['name'])    ? replaceRamenData($ramen['name'])    : '情報なし')."\n\n";
	$ret .= '■URL'   ."\n";
	$ret .= (!empty($ramen['url'])     ? makeGoogleShortURL($ramen['url'])   : '情報なし')."\n\n";
	$ret .= '■住所'  ."\n";
	$ret .= (!empty($ramen['address']) ? replaceRamenData($ramen['address']) : '情報なし')."\n\n";
	$ret .= '■定休日'."\n";
	$ret .= (!empty($ramen['holiday']) ? replaceRamenData($ramen['holiday']) : '情報なし')."\n\n";
	$ret .= ('Powered by ぐるなび');

	return $ret;
}

/**
 * URLを除いて、たまに改行コードが入っているので、置換して返却
 * @param  String $ramen
 * @return String $ramen
 */
function replaceRamenData($ramen)
{
	$search = array('<br>', '<BR>');
	$ramen = str_replace($search, "\n", $ramen);

	return $ramen;
}

/**
 * GOOGLEのAPIでURLを短縮化して返却する
 * @param string $url
 * @return string $response
 */
function makeGoogleShortURL($url)
{
	$postData = array('longUrl'=>$url);
	$uri = GOOGLE_URLSHORT_URI . '?key=' . GOOGLE_URLSHORT_KEY;

	$response = curl_execute('POST', $uri, $postData);

	$response = json_decode($response);

	if(property_exists($response,'error')){
		error_log("GOOGLE SHORT API EXCEPTION status:".$response->error->code . ' message:'.$response->error->message);
		//return "GOOGLEAPIのエラーによりURLを取得できませんでした";
		// エラーの場合は長い方えお返す
		return $url;
	}

	return $response->id;

}

/**
 * グルナビAPIに渡す用のパラメータを作成する。
 * @param object $event
 * @return string $uri
 */
function makeGnaviURI($event)
{

	$latitude  = $event->getLatitude();
	$longitude = $event->getLongitude();

	$uri  = GNAVI_URI                             .'?';
	$uri .= 'keyid='       .GNAVI_ACCESS_KEY      .'&';
	$uri .= 'latitude='    .$event->getLatitude() .'&';
	$uri .= 'longitude='   .$event->getLongitude().'&';
	$uri .= 'category_l='  .GNAVI_CATEGORY_L      .'&';
	$uri .= 'hit_per_page='.GNAVI_HIT_PER_PAGE;

	return $uri;
}
