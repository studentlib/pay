
<?php
include_once('./src/Google_Client.php');
include_once('./src/contrib/Google_AndroidpublisherService.php');

$ANDROIDUsertoken='{"orderId":"GPA.3399-6482-6447-70996","packageName":"com.gamecaff.tkhero","productId":"com.gamecaff.tkhero.600","purchaseTime":1514188653491,"purchaseState":0,"developerPayload":"e76a7523a9dacb865a7ba135c82b3491797a1b74","purchaseToken":"gjjdbglcjofeiocohmebaneh.AO-J1Ozel6LdGrNRUV4RFCd22AUYk5yECovGcvOOOvEr1706huC-V8xpkL9dJ97SYauXhr7jkK3DXKx9t4-MlXfOpwGEvCWQV7TozwxMwuMksQypUOQigCif5GmVPzYLy1SOfy9W8zH_"}';

$user_token= json_decode($ANDROIDUsertoken,true);

$CLIENT_ID = '274951158602-cq6otu6sg5qgbrr4jqs1r2ltso9q9k2b.apps.googleusercontent.com';

$SERVICE_ACCOUNT_NAME = 'txyxjp001@api-7062615535232458999-640844.iam.gserviceaccount.com';
$KEY_FILE = './key.p12';

$client = new Google_Client();
$client->setApplicationName($user_token['packageName']);
$client->setClientId($CLIENT_ID);

$key = file_get_contents($KEY_FILE);
$auth = new Google_AssertionCredentials(
    $SERVICE_ACCOUNT_NAME,
    array('https://www.googleapis.com/auth/androidpublisher'),
    $key);

$client->setAssertionCredentials($auth);

$AndroidPublisherService = new Google_AndroidPublisherService($client);
$res = $AndroidPublisherService->inapppurchases->get($user_token['packageName'], $user_token['productId'], $user_token['purchaseToken']);

// var_dump($res['purchaseState']); 
 //$data = json_decode($res,true);
 if(0 == $res['purchaseState'] && (1 == $res['consumptionState'])){
   print_r(" check success \n");
   return true;
 }

?>


