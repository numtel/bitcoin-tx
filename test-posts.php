<?
error_reporting(E_ALL);
ini_set("display_errors", 1);

include 'inc/btc.php';
include 'inc/post.php';
$testFriends=array('11111','11112','11113','11114','11115','11116');

$tests=array(
	array('action'=>'send_to_fb','fb_recip'=>'11111','amount'=>'1.2'),
	array('action'=>'send_to_fb','fb_recip'=>'11113','amount'=>'1.2'),
	array('action'=>'send_to_fb','fb_recip'=>'','amount'=>'1.2'),
	array('action'=>'send_to_fb','fb_recip'=>'11113','amount'=>'0'),
	array('action'=>'send_to_fb','fb_recip'=>'11113','amount'=>''),
	array('action'=>'send_to_fb','fb_recip'=>'11113','amount'=>'-1.2'),
	array('action'=>'send_to_fb','fb_recip'=>'111152','amount'=>'1.2'),
	array('action'=>'send_to_fb','fb_recip'=>'11111','amount'=>'1,111.2'),
	array('action'=>'send_to_btc','fb_recip'=>'11111','amount'=>'1.2'),
	array('action'=>'send_to_btc','btc_addr'=>'11111','amount'=>'1.2'),
	array('action'=>'send_to_btc','btc_addr'=>'1DUerWAepez56PziZTnSBzT2jFTF4722eK','amount'=>'1.2'),
	array('action'=>'send_to_btc','btc_addr'=>'1DUerWAepez56PziZTnSBzT2jFTF4722eK23','amount'=>'1.2'),
	array('action'=>'','fb_recip'=>'11113','amount'=>'1.2'),
);

echo '<table border="1"><tr><th>Test</th><th>Results</th></tr>';
foreach($tests as $test){
	echo '<tr><td><pre>';
	print_r($test);
	echo '</pre></td><td>';
	var_dump(validate_post($test,$testFriends));
	echo '</td></tr>';
}
echo '</table>';


