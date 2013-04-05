<?

require 'settings.php';
require 'sdk/src/facebook.php';


$strings=array("Error generating new Bitcoin address.",
"New Bitcoin Address Generated!",
"Error: Invalid recipient!",
"Error: Invalid Bitcoin Address!",
"Error: Must specify an amount!",
"Error: Invalid send amount!",
"Error: Send amount must be at least 1 satoshi (1/100000000 BTC)",
"Error: Insufficient Funds for Transfer",
"Bitcoins sent successfully!",
'This user has not installed this app and will not be notified automatically. Please send them a message so they can access their Bitcoins.',
'Your Balance:',
'Fund Your Account',
'QR Code to send bitcoins to fund your account',
'Add Bitcoins to your account using the following address:',
'Need to buy Bitcoins?',
'Generate new Address',
'Send Bitcoins to a Facebook Friend',
'Send Bitcoins to a Bitcoin Address',
'You have no friends.',
'Recipient:',
'Amount:',
'Fee added to this transaction.',
'Send',
'Address:',
'Fee subtracted from this transaction.',
'Transaction History',
'Date',
'Description',
'Deposit',
'Withdrawal',
'Balance',
'No Transactions',
'Deposit from:',
'Withdrawal to:',
'Older',
'Newer',
'Want to show support?',
'Post to Wall',
'Send Message',
'Send Requests',
'Send Bitcoins to ',
'Submit a translation of the interface');

$languages=array (
  'af_ZA' => 'Afrikaans',
  'ar_AR' => 'Arabic',
  'az_AZ' => 'Azerbaijani',
  'be_BY' => 'Belarusian',
  'bg_BG' => 'Bulgarian',
  'bn_IN' => 'Bengali',
  'bs_BA' => 'Bosnian',
  'ca_ES' => 'Catalan',
  'cs_CZ' => 'Czech',
  'cy_GB' => 'Welsh',
  'da_DK' => 'Danish',
  'de_DE' => 'German',
  'el_GR' => 'Greek',
  'en_GB' => 'English (UK)',
  'en_PI' => 'English (Pirate)',
  'en_UD' => 'English (Upside Down)',
  'en_US' => 'English (US)',
  'eo_EO' => 'Esperanto',
  'es_ES' => 'Spanish (Spain)',
  'es_LA' => 'Spanish',
  'et_EE' => 'Estonian',
  'eu_ES' => 'Basque',
  'fa_IR' => 'Persian',
  'fb_LT' => 'Leet Speak',
  'fi_FI' => 'Finnish',
  'fo_FO' => 'Faroese',
  'fr_CA' => 'French (Canada)',
  'fr_FR' => 'French (France)',
  'fy_NL' => 'Frisian',
  'ga_IE' => 'Irish',
  'gl_ES' => 'Galician',
  'he_IL' => 'Hebrew',
  'hi_IN' => 'Hindi',
  'hr_HR' => 'Croatian',
  'hu_HU' => 'Hungarian',
  'hy_AM' => 'Armenian',
  'id_ID' => 'Indonesian',
  'is_IS' => 'Icelandic',
  'it_IT' => 'Italian',
  'ja_JP' => 'Japanese',
  'ka_GE' => 'Georgian',
  'km_KH' => 'Khmer',
  'ko_KR' => 'Korean',
  'ku_TR' => 'Kurdish',
  'la_VA' => 'Latin',
  'lt_LT' => 'Lithuanian',
  'lv_LV' => 'Latvian',
  'mk_MK' => 'Macedonian',
  'ml_IN' => 'Malayalam',
  'ms_MY' => 'Malay',
  'nb_NO' => 'Norwegian (bokmal)',
  'ne_NP' => 'Nepali',
  'nl_NL' => 'Dutch',
  'nn_NO' => 'Norwegian (nynorsk)',
  'pa_IN' => 'Punjabi',
  'pl_PL' => 'Polish',
  'ps_AF' => 'Pashto',
  'pt_BR' => 'Portuguese (Brazil)',
  'pt_PT' => 'Portuguese (Portugal)',
  'ro_RO' => 'Romanian',
  'ru_RU' => 'Russian',
  'sk_SK' => 'Slovak',
  'sl_SI' => 'Slovenian',
  'sq_AL' => 'Albanian',
  'sr_RS' => 'Serbian',
  'sv_SE' => 'Swedish',
  'sw_KE' => 'Swahili',
  'ta_IN' => 'Tamil',
  'te_IN' => 'Telugu',
  'th_TH' => 'Thai',
  'tl_PH' => 'Filipino',
  'tr_TR' => 'Turkish',
  'uk_UA' => 'Ukrainian',
  'vi_VN' => 'Vietnamese',
  'zh_CN' => 'Simplified Chinese (China)',
  'zh_HK' => 'Traditional Chinese (Hong Kong)',
  'zh_TW' => 'Traditional Chinese (Taiwan)',
);

//connect to facebook
$facebook = new Facebook($fb_settings);
$user = $facebook->getUser();

if($user){
	try {
		$user_profile = $facebook->api('/me');
	} catch (FacebookApiException $e) {
		error_log($e);
		$user = null;
	}
}

function unicode_escape($str){
	$working = json_encode($str);
	$working = preg_replace('/\\\u([0-9a-z]{4})/', '&#x$1;', $working);
	return json_decode($working);
}

function entity_encode_deep(&$input) {
    if (is_string($input)) {
        $input = unicode_escape($input);
    } else if (is_array($input)) {
        foreach ($input as &$value) {
            entity_encode_deep($value);
        }

        unset($value);
    } else if (is_object($input)) {
        $vars = array_keys(get_object_vars($input));

        foreach ($vars as $var) {
            entity_encode_deep($input->$var);
        }
    }
}

if($_POST){
	//connect to db
	$db_url=parse_url(getenv("CLEARDB_DATABASE_URL"));
	$db_dns='mysql:host='.$db_url["host"].';dbname='.substr($db_url["path"],1);
	$db=new PDO($db_dns, $db_url["user"], $db_url["pass"]);
	
	
	$data=$_POST;
	entity_encode_deep($data);
	$insert_query=$db->prepare('insert into `trans` (`data`) values (?)');
	$insert_query->execute(array(serialize($data)));
	$post_status="Thank you for your support! Your response will be processed.";
}

?>
<!DOCTYPE html>
<html xmlns:fb="http://www.facebook.com/2008/fbml">
	<head>
		<meta charset="utf-8" />
		<title>Bitcoin Transactions</title>
		<link rel="stylesheet" href="stylesheets/reset.css" type="text/css" />
		<link rel="stylesheet" href="stylesheets/btc.css" type="text/css" />
	</head>
	<body>
		<div id="container">
		<?php if($user): ?>
			<form method="post" id="translate">
				<h3>Provide a Translation</h3>
				<div class="field">
					<label for="language">Language:</label>
					<select id="language" name="language">
						<?foreach($languages as $lang_code=>$lang):?>
							<option value="<?=$lang_code?>"><?=$lang?></option>
						<?endforeach;?>
					</select>
				</div>
				<div class="field-scroll">
				<? foreach($strings as $i=>$str): ?>
					<div class="string">
						<span><?=$str?></span>
						<input type="hidden" name="str_orig_<?=$i?>" value="<?=htmlentities($str)?>" />
						<input name="str_tr_<?=$i?>" />
					</div>
				<? endforeach; ?>
				</div>
				<a href="/" class="back-to-home">Return to Transactions Home without Submitting</a>
				<button type="submit">Submit</button>
				<? if(isset($post_status)): ?>
					<div id="form_status"><?=$post_status?> <a href="/">Return to Transactions Home</a></div>
				<? endif; ?>
			</form>
		<?php else: ?>
			<p>In order to use this application, you must be logged in and registered.<br />Click on the login button below and accept the message to begin using Bitcoin Transactions.</p>
			<fb:login-button></fb:login-button>
		<?php endif; ?>
		</div>
		<div id="fb-root"></div>
		<script>
			window.fbAsyncInit = function() {
				FB.init({
					appId: '<?php echo $facebook->getAppID() ?>',
					cookie: true,
					xfbml: true,
					oauth: true
				});
				FB.getLoginStatus(function(response) {
					if (response.status !== 'connected') {
						FB.Event.subscribe('auth.login', function(response) {
							window.location.reload();
						});
					}
				});
				
			};
			(function() {
				var e = document.createElement('script'); e.async = true;
				e.src = document.location.protocol +
				'//connect.facebook.net/en_US/all.js';
				document.getElementById('fb-root').appendChild(e);
			}());

		</script>
	</body>
</html>
