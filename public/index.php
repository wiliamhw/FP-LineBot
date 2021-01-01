$httpClient
<?php
require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\ImageMessageBuilder;
use \LINE\LINEBot\MessageBuilder\VideoMessageBuilder;
use \LINE\LINEBot\MessageBuilder\AudioMessageBuilder;
use \LINE\LINEBot\SignatureValidator as SignatureValidator;

// If request simulation --> true
// else set to false
$pass_signature = true;

// set LINE channel_access_token and channel_secret
$channel_access_token = "O61KVxDNK8SFhtjMZ7vjWoatFexye3BL3D6ryMTIstidU2u+DHFg1OTefzlLOpgXGbFbX7kVcWi0REkepR5xX9+m0ZQPP4WtF9d2CPiIq1c4N7AfMtCFFTgZEoGNgPGxuLuEXVv7FamvQ7PprsZfDQdB04t89/1O/w1cDnyilFU=";
$channel_secret = "3511922bd165643e115dc07412a17040";

// inisiasi objek bot
$httpClient = new CurlHTTPClient($channel_access_token);
$bot = new LINEBot($httpClient, ['channelSecret' => $channel_secret]);

$app = AppFactory::create();
$app->setBasePath("/public");

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello World!");
    return $response;
});

// buat route untuk webhook
$app->post('/webhook', function (Request $request, Response $response) use ($channel_secret, $bot, $httpClient, $pass_signature) {
    // get request body and line signature header
    $body = $request->getBody();
    $signature = $request->getHeaderLine('HTTP_X_LINE_SIGNATURE');

    // log body and signature
    file_put_contents('php://stderr', 'Body: ' . $body);

    if ($pass_signature === false) {
        // is LINE_SIGNATURE exists in request header?
        if (empty($signature)) {
            return $response->withStatus(400, 'Signature not set');
        }

        // is this request comes from LINE?
        if (!SignatureValidator::validateSignature($body, $channel_secret, $signature)) {
            return $response->withStatus(400, 'Invalid signature');
        }
    }

    // store JSON data
    $data = json_decode($body, true);

    // Reply user input
    if (is_array($data['events'])) {
        foreach ($data['events'] as $event) {
            if ($event['type'] == 'message') {
                // get berkas selain text dari user
                if (
                    $event['message']['type'] == 'image' or
                    $event['message']['type'] == 'video' or
                    $event['message']['type'] == 'audio' or
                    $event['message']['type'] == 'file'
                ) {
                    $contentURL = " https://line-final-project.herokuapp.com/public/content/" . $event['message']['id'];
                    $contentType = ucfirst($event['message']['type']);
                    $result = $bot->replyText(
                        $event['replyToken'],
                        $contentType . " yang Anda kirim bisa diakses dari link:\n " . $contentURL
                    );
                    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($result->getHTTPStatus());
                } else {

                    // make multiMessage
                    $multiMessageBuilder = new multiMessageBuilder();

                    if (strtolower($event['message']['text']) == 'perkenalan') {
                        $textMessageBuilder = new TextMessageBuilder(
                            "Bot ini dibuat untuk memenuhi final project pelatihan Line Bot.\n" .
                            "Bot ini berfungsi untuk menapilkan stiker, menyimpan berkas, dan membuat pola bangunan yang dapat di copy-paste.\n"
                        );
                        $multiMessageBuilder->add($textMessageBuilder);
                    }

                    if (strtolower($event['message']['text']) == 'help' || strtolower($event['message']['text']) == 'perkenalan') {
                        // send text
                        $textMessageBuilder = new TextMessageBuilder(
                            "List perintah:\n" .
                                "   * Perkenalan\n" .
                                "   * Help\n" .
                                "   * stiker <package_id> <sticler_id>\n" .
                                "   * piramid <tinggi>\n" .
                                "   * berlian <tinggi>\n" .
                            "Untuk menyimpan berkas, kirim berkas tersebut ke bot init.\n"
                        );
                        $textMessageBuilder1 = new TextMessageBuilder(
                            "Id stiker bisa dilihat di:\n" .
                                "https://devdocs.line.me/files/sticker_list.pdf"
                        );
                        $multiMessageBuilder->add($textMessageBuilder);
                        $multiMessageBuilder->add($textMessageBuilder1);
                    } else if (substr(strtolower($event['message']['text']), 0, 6) === 'stiker') {
                        // send sticker
                        $pieces = explode(" ", $event['message']['text']);

                        if (sizeof($pieces) == 3 && is_numeric($pieces[1]) && is_numeric($pieces[2])) {
                            $packageId = $pieces[1];
                            $stickerId = $pieces[2];

                            if ($packageId < 640 && $stickerId < 5) {
                                $stickerMessageBuilder = new StickerMessageBuilder($packageId, $stickerId);
                                $multiMessageBuilder->add($stickerMessageBuilder);
                            } else {
                                $textMessageBuilder = new TextMessageBuilder(
                                    "PackageID atau stickerID tidak terdefinisi."
                                );
                                $multiMessageBuilder->add($textMessageBuilder);
                            }
                        }
                    } else if (substr(strtolower($event['message']['text']), 0, 7) === 'piramid') {
                        $pieces = explode(" ", $event['message']['text']);

                        if (sizeof($pieces) == 2 && is_numeric($pieces[1])) {
                            $Height = $pieces[1];
                            $result = '';

                            for ($i = 0; $i < $Height; $i++) {
                                // Bagian kiri
                                for ($j = 0; $j < $Height - $i - 1; $j++) {
                                    $result .= "  ";
                                }
                                for ($k = 0; $k < $i + 1; $k++) {
                                    $result .= "#";
                                }
                                // Spasi tengah
                                $result .= "  ";
                                // Bagian kanan
                                for ($k = 0; $k < $i + 1; $k++) {
                                    $result .= "#";
                                }
                                $result .= "\n";
                            }
                            $textMessageBuilder = new TextMessageBuilder($result);
                            $multiMessageBuilder->add($textMessageBuilder);
                        }
                    } else if (substr(strtolower($event['message']['text']), 0, 7) === 'berlian') {
                        $pieces = explode(" ", $event['message']['text']);

                        if (sizeof($pieces) == 2 && is_numeric($pieces[1])) {
                            $Height = $pieces[1] - 1;
                            $result = '';

                            for ($i = 0; $i < $Height; $i++) {
                                for ($j = 0; $j < $Height; $j++) {
                                    $result .= "   ";
                                }
                                for ($k = 0; $k < $i + 1; $k++) {
                                    $result .= "#";
                                }
                                $result .= "\n";
                                $Height--;
                            }
                            $Height = 0;
                            for ($i = $pieces[1]; $i > 0; $i--)  
                            {  
                                for ($j = 0; $j < $Height - 1; $j++) {
                                    $result .= "   ";
                                } 
                          
                                // Print i stars  
                                for ($j = 0; $j < $i; $j++) {
                                    $result .= "*";
                                }
                                $result .= "\n";
                                $Height++;
                            }  

                            $textMessageBuilder = new TextMessageBuilder($result);
                            $multiMessageBuilder->add($textMessageBuilder);
                        } 
                    } else {
                        $textMessageBuilder = new TextMessageBuilder(
                            "Penulisan perintah salah.\n" .
                                "Kirim 'Help' untuk informasi perintah.\n"
                        );
                        $multiMessageBuilder->add($textMessageBuilder);
                    }

                    // store result
                    $result = $bot->replyMessage($event['replyToken'], $multiMessageBuilder);

                    // write to JSON
                    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($result->getHTTPStatus());
                }
            }
        }
        return $response->withStatus(200, 'for Webhook!'); //buat ngasih response 200 ke pas verify webhook
    }
});

$app->run();
