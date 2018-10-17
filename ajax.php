<?php

use Aws\S3\S3Client;

$settings = (array) get_option('simple_s3_upload_settings');

// These assume you have the associated AWS keys stored in
// the associated system environment variables
$clientPrivateKey = isset($settings['aws_secret_key']) ? $settings['aws_secret_key'] : null;

// These two keys are only needed if the delete file feature is enabled
// or if you are, for example, confirming the file size in a successEndpoint
// handler via S3's SDK, as we are doing in this example.
//$serverPublicKey = ''; isset($settings['aws_private_access_key']) ? $settings['aws_private_access_key'] : null;
//$serverPrivateKey = ''; isset($settings['aws_private_secret_key']) ? $settings['aws_private_secret_key'] : null;

// The following variables are used when validating the policy document
// sent by the uploader.
$expectedBucketName = isset($settings['s3_bucket']) ? $settings['s3_bucket'] : null;
$expectedHostName = $_SERVER['HTTP_HOST']; // v4-only

// $expectedMaxSize is the value you set the sizeLimit property of the
// validation option. We assume it is `null` here. If you are performing
// validation, then change this to match the integer value you specified
// otherwise your policy document will be invalid.
// http://docs.fineuploader.com/branch/develop/api/options.html#validation-option
$expectedMaxSize = (isset($_ENV['S3_MAX_FILE_SIZE']) ? $_ENV['S3_MAX_FILE_SIZE'] : null);

/**
 * This is the action that takes place when a user closes a single alert
 */
function simple_s3_upload_cors() {
	$method = $_SERVER['REQUEST_METHOD'];

	// This second conditional will only ever evaluate to true if
	// the delete file feature is enabled
	if ($method == "DELETE") {
//		deleteObject();
	}
	// This is all you really need if not using the delete file feature
	// and not working in a CORS environment
	else if	($method == 'POST') {

		// Assumes the successEndpoint has a parameter of "success" associated with it,
		// to allow the server to differentiate between a successEndpoint request
		// and other POST requests (all requests are sent to the same endpoint in this example).
		// This condition is not needed if you don't require a callback on upload success.
		if (isset($_REQUEST["success"])) {
			verifyFileInS3();
		}
		else {
			signRequest();
		}
	}

	exit();
}
add_action('wp_ajax_simple_s3_upload_cors', 'simple_s3_upload_cors');


function signRequest() {
	header('Content-Type: application/json');

	$responseBody = file_get_contents('php://input');
	$contentAsObject = json_decode($responseBody, true);
	$jsonContent = json_encode($contentAsObject);

	$headersStr = $contentAsObject["headers"];
	if ($headersStr) {
		signRestRequest($headersStr);
	}
	else {
		signPolicy($jsonContent);
	}
}

function signRestRequest($headersStr) {
	$version = isset($_REQUEST["v4"]) ? 4 : 2;
	if (isValidRestRequest($headersStr, $version)) {
		if ($version == 4) {
			$response = array('signature' => signV4RestRequest($headersStr));
		}
		else {
			$response = array('signature' => sign($headersStr));
		}

		echo json_encode($response);
	}
	else {
		echo json_encode(array("invalid" => true));
	}
}

function isValidRestRequest($headersStr, $version) {
	if ($version == 2) {
		global $expectedBucketName;
		$pattern = "/\/$expectedBucketName\/.+$/";
	}
	else {
		global $expectedHostName;
		$pattern = "/host:$expectedHostName/";
	}

	preg_match($pattern, $headersStr, $matches);

	return count($matches) > 0;
}

function signPolicy($policyStr) {
	$policyObj = json_decode($policyStr, true);

	if (isPolicyValid($policyObj)) {
		$encodedPolicy = base64_encode($policyStr);

		if (isset($_REQUEST["v4"])) {
			$response = array('policy' => $encodedPolicy, 'signature' => signV4Policy($encodedPolicy, $policyObj));
		}
		else {
			$response = array('policy' => $encodedPolicy, 'signature' => sign($encodedPolicy));
		}
		echo json_encode($response);

	}
	else {
		echo json_encode(array("invalid" => true));
	}
}

function isPolicyValid($policy) {
	global $expectedMaxSize, $expectedBucketName;

	$conditions = $policy["conditions"];
	$bucket = null;
	$parsedMaxSize = null;

	for ($i = 0; $i < count($conditions); ++$i) {
		$condition = $conditions[$i];

		if (isset($condition["bucket"])) {
			$bucket = $condition["bucket"];
		}
		else if (isset($condition[0]) && $condition[0] == "content-length-range") {
			$parsedMaxSize = $condition[2];
		}
	}

	return $bucket == $expectedBucketName && $parsedMaxSize == (string)$expectedMaxSize;
}

function sign($stringToSign) {
	global $clientPrivateKey;

	return base64_encode(hash_hmac(
		'sha1',
		$stringToSign,
		$clientPrivateKey,
		true
	));
}

function signV4Policy($stringToSign, $policyObj) {
	global $clientPrivateKey;

	foreach ($policyObj["conditions"] as $condition) {
		if (isset($condition["x-amz-credential"])) {
			$credentialCondition = $condition["x-amz-credential"];
		}
	}

	$pattern = "/.+\/(.+)\\/(.+)\/s3\/aws4_request/";
	preg_match($pattern, $credentialCondition, $matches);

	$dateKey = hash_hmac('sha256', $matches[1], 'AWS4' . $clientPrivateKey, true);
	$dateRegionKey = hash_hmac('sha256', $matches[2], $dateKey, true);
	$dateRegionServiceKey = hash_hmac('sha256', 's3', $dateRegionKey, true);
	$signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);

	return hash_hmac('sha256', $stringToSign, $signingKey);
}

function signV4RestRequest($rawStringToSign) {
	global $clientPrivateKey;

	$pattern = "/.+\\n.+\\n(\\d+)\/(.+)\/s3\/aws4_request\\n(.+)/s";
	preg_match($pattern, $rawStringToSign, $matches);

	$hashedCanonicalRequest = hash('sha256', $matches[3]);
	$stringToSign = preg_replace("/^(.+)\/s3\/aws4_request\\n.+$/s", '$1/s3/aws4_request'."\n".$hashedCanonicalRequest, $rawStringToSign);

	$dateKey = hash_hmac('sha256', $matches[1], 'AWS4' . $clientPrivateKey, true);
	$dateRegionKey = hash_hmac('sha256', $matches[2], $dateKey, true);
	$dateRegionServiceKey = hash_hmac('sha256', 's3', $dateRegionKey, true);
	$signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);

	return hash_hmac('sha256', $stringToSign, $signingKey);
}

// This is not needed if you don't require a callback on upload success.
function verifyFileInS3() {
	global $expectedMaxSize;

	$bucket = $_REQUEST["bucket"];
	$key = $_REQUEST["key"];

	// If utilizing CORS, we return a 200 response with the error message in the body
	// to ensure Fine Uploader can parse the error message in IE9 and IE8,
	// since XDomainRequest is used on those browsers for CORS requests.  XDomainRequest
	// does not allow access to the response body for non-success responses.
	if (isset($expectedMaxSize) && getObjectSize($bucket, $key) > $expectedMaxSize) {
		// You can safely uncomment this next line if you are not depending on CORS
		header("HTTP/1.0 500 Internal Server Error");
//		deleteObject();
		echo json_encode(array("error" => "File is too big!", "preventRetry" => true));
	}
	else {
		echo json_encode(true); //Always return true here b/c we don't need to verify for our purposes=
	}
}

?>