<?php
//error_reporting(E_ALL);
//ini_set("display_errors", 1);

require 'settings.php';
require 'sdk/src/facebook.php';

	
//connect to facebook
$facebook = new Facebook($fb_settings);
$user = $facebook->getUser();


if(isset($_GET['sid'])){
	$a = session_id($_GET['sid']);
}else{
	$a = session_id();
}
if ($a == '') session_start();
if (!isset($_SESSION['safety'])) {
	session_regenerate_id(true);
	$_SESSION['safety'] = true;
}
$_SESSION['sessionid'] = session_id();

//localization
$cur_language=array();
function _tr($str){
	global $cur_language;
	foreach($cur_language as $index=>$value){
		if($value===$str){
			return $cur_language['str_tr_'.substr($index,9)];
		}
	}
	return $str;
}

//connect to db
$db_url=parse_url(getenv("CLEARDB_DATABASE_URL"));
$db_dns='mysql:host='.$db_url["host"].';dbname='.substr($db_url["path"],1);
$db=new PDO($db_dns, $db_url["user"], $db_url["pass"]);

//error recording
function record_error($blob){
	global $db;
	if(isset($_SERVER ['HTTP_X_FORWARDED_FOR'])){
		$clientIP = $_SERVER ['HTTP_X_FORWARDED_FOR'];
	}elseif(isset($_SERVER ['HTTP_X_REAL_IP'])){
		$clientIP = $_SERVER ['HTTP_X_REAL_IP'];
	}else{
		$clientIP = $_SERVER['REMOTE_ADDR'];
	}
	$error_query=$db->prepare('insert into `error_log` (`data`,`date`,`ip`) values (?, ?, ?)');
	return $error_query->execute(array($blob,date('Y-m-d H:i:s'),$clientIP));
}

//process blockchain callback from fund addage
if(
	isset($_GET['create_addr']) && $_GET['create_addr'] && 
	isset($_GET['fb_id']) && 
	isset($_GET['input_address']) && 
	isset($_GET['secret']) && 
	isset($_GET['value']) && 
	isset($_GET['transaction_hash']) && 
	isset($_GET['confirmations'])
){
	$test_query=$db->prepare('select * from `addr` where `fb_id` = ? and `btc_addr` = ? and `secret` = ?');
	$test_query->execute(array($_GET['fb_id'],$_GET['input_address'],$_GET['secret']));
	$test_data=$test_query->fetchAll();
	if(count($test_data)>0){
		if($_GET['confirmations']>=6){
			//declare transaction approved
			$btc_update_query=$db->prepare('update `tx` SET `type`=1 WHERE `hash`=?');
			$btc_update_query->execute(array($_GET['transaction_hash']));
			die('*ok*');
		}else{
			//make sure not already inserted
			$test_insert_query=$db->prepare('select * from `tx` where `hash` = ?');
			$test_insert_query->execute(array($_GET['transaction_hash']));
			$test_insert_data=$test_insert_query->fetchAll();
			if(count($test_insert_data)===0){
				//create pending item
				$btc_insert_query=$db->prepare('insert into `tx` (`fb_id`,`type`,`btc_addr`,`date`,`amount`,`hash`) values (?, ?, ?, ?, ?, ?)');
				$btc_insert_query->execute(array(
					$_GET['fb_id'],
					'4',
					$_GET['input_address'],
					date('Y-m-d H:i:s'),
					$_GET['value'],
					$_GET['transaction_hash']
				));
			}
			die('');
		}
	}else{
		//record potential DDOS
		record_error(serialize($_GET));
		die('error');
	}
}


//load cached rates or reload if old enough
$rate_query=$db->prepare('select `data`, `timestamp` from `rates` order by `id` desc limit 1');
$rate_query->execute(array());
$rate_data=$rate_query->fetchAll();
if(count($rate_data)===0 || $rate_data[0]['timestamp']*1<strtotime('-15 minutes')){
	$new_rates=file_get_contents('https://blockchain.info/ticker');
	if($new_rates!==false){
		$rate_insert_query=$db->prepare('insert into `rates` (`data`,`timestamp`) values (?, ?)');
		$rate_insert_query->execute(array($new_rates, time()));
		$rates=$new_rates;
	}else{
		//failure to load rates
		if(count($rate_data)===0){
			//nothing in db, fail!
			$rates='{}';
		}else{
			//give latest
			$rates=$rate_data[0]['data'];
		}
	}
}else{
	//give cached rates
	$rates=$rate_data[0]['data'];
}


if($user){
	try {
		$user_profile = $facebook->api('/me');
		
		if(file_exists('trans/'.$user_profile['locale'].'.txt')){
			$cur_language=unserialize(file_get_contents('trans/'.$user_profile['locale'].'.txt'));
		}

	} catch (FacebookApiException $e) {
		error_log($e);
		$user = null;
	}
}

if($user){
	$friends=$facebook->api('/me/friends');
	$friendIds=array();
	foreach($friends['data'] as $friend){
		$friendIds[]=$friend['id'];
	}
	
	//load this user
	function user_activated($fb_id=false){
		global $user, $db;
		if($fb_id===false) $fb_id=$user;
		$user_query=$db->prepare('select * from `user` where `fb_id` = ?');
		$user_query->execute(array($fb_id));
		$user_row=$user_query->fetchAll();
		if(count($user_row)===0) return false;
		return $user_row[0]['activated'];
	}
	if(user_activated()===false){
		//user's first access!
		$user_create_query=$db->prepare('insert into `user` (`fb_id`,`activated`) values (?, ?)');
		$user_create_query->execute(array($user,date('Y-m-d H:i:s')));
	}
	
	//revert old transactions
	$revert_query=$db->prepare("SELECT `tx`.`tx_id`,`tx`.`fb_id`,`tx`.`amount` FROM `tx` left join `user` on `user`.`fb_id`=`tx`.`fb_id` where `fb_recip` = ? and `date` < ? and `type`=1 and `user`.`activated` is null");
	$revert_query->execute(array($user, date('Y-m-d',strtotime('-'.$revert_duration))));
	$revert_data=$revert_query->fetchAll();

	foreach($revert_data as $tx){
		$revert_tx_query=$db->prepare("UPDATE `tx` SET `fb_id`='revert', `type`=3, `hash`=? WHERE `tx_id`=?");
		if($revert_tx_query->execute(array($tx['fb_id'],$tx['tx_id']))){
			$revert_tx_query_2=$db->prepare('insert into `tx` (`fb_id`,`type`,`btc_addr`,`fb_recip`,`date`,`amount`,`hash`) values (?, ?, ?, ?, ?, ?, ?)');
			$revert_tx_query_2->execute(array($user,'1',null,$tx['fb_id'],date('Y-m-d H:i:s'),$tx['amount'],'revert'));
		}
	}

	
	//load transactions
	function load_tx(){
		global $db, $user, $tx_data, $tx_count, $balance, $fb_tx_fee, 
			$display_count, $ordered_tx, $last_page, $display_page, $tx_per_page;
		$tx_query=$db->prepare('select * from `tx` where `fb_id` = ? order by `tx_id` asc');
		$tx_query->execute(array($user));
		$tx_data=$tx_query->fetchAll();
		
		//calculate balance
		$balance='0';
		$display_count=$tx_count=count($tx_data);
		for($i=0;$i<$tx_count;++$i){
			if((int)$tx_data[$i]['type']===1){
				$balance=$tx_data[$i]['balance']=$balance+$tx_data[$i]['amount'];
			}elseif((int)$tx_data[$i]['type']===2){
				if($tx_data[$i]['fb_recip']==='fee'){
					--$display_count;
				}else{
					$c_tx_fee=$i+1<$tx_count && $tx_data[$i+1]['fb_recip']==='fee' ? $tx_data[$i+1]['amount'] : 0;
					$balance=$tx_data[$i]['balance']=$balance-$tx_data[$i]['amount']-$c_tx_fee;
				}
			}
		}
		$ordered_tx=array_reverse($tx_data);
		$last_page=(int)ceil($display_count/$tx_per_page);
		$display_page=isset($_GET['p']) && is_numeric($_GET['p']) && (int)$_GET['p']<=$last_page ? (int)$_GET['p'] : (int)1;
	}
	load_tx();
	
	//get current btc send address
	$addr_query=$db->prepare('select `btc_addr` from `addr` where `fb_id` = ? order by `date` desc limit 1');
	$addr_query->execute(array($user));
	$addr_data=$addr_query->fetchAll();

	function random_string($length = 20) {
		$characters = '1234567890abcdefghijklmnopqrstuvwxyz';
		$string = "";    
		for ($p = 0; $p < $length; $p++) {
		    $string .= $characters[mt_rand(0, strlen($characters)-1)];
		}
		return $string;
	}
	
	function create_btc_send_addr(){
		global $db, $user, $bucket_addr, $callback_url;
		//contact blockchain
		$secret = random_string(20);
		$callback_param = '?create_addr=true&fb_id='.$user.'&secret='.$secret;
		$root_url = 'https://blockchain.info/api/receive';
		$parameters = 'method=create&address=' . $bucket_addr .'&shared=false&callback='. urlencode($callback_url.$callback_param);
		$response = file_get_contents($root_url . '?' . $parameters);
		$object = json_decode($response);
		
		if(!is_object($object) || !property_exists($object,'input_address')) return false;
		
		//update db
		$new_addr_query=$db->prepare('insert into `addr` (`fb_id`,`btc_addr`,`date`,`secret`) values (?, ?, ?, ?)');
		if(!$new_addr_query->execute(array($user, $object->input_address, date('Y-m-d H:i:s'), $secret))) return false;

		//all worked
		return $object->input_address;
	}
	
	if(count($addr_data)===0){
		$btc_addr=create_btc_send_addr();
		if($btc_addr===false){
			$post_status=_tr("Error generating new Bitcoin address.");
		}
	}else{
		$btc_addr=$addr_data[0]['btc_addr'];
	}
	
	if($_POST && isset($_POST['action'])){
		switch($_POST['action']){
			case 'new_send_addr':
				$btc_addr=create_btc_send_addr();
				if($btc_addr===false){
					$post_status=_tr("Error generating new Bitcoin address.");
				}else{
					$post_status=_tr("New Bitcoin Address Generated!");
				}
				break;
			case 'send_to_fb':
			case 'send_to_btc':
				$is_intra_fb=$_POST['action']==='send_to_fb';
				if($is_intra_fb && 
					(
						!isset($_POST['fb_recip']) || 
						!in_array($_POST['fb_recip'],$friendIds)
					)
				){
					$post_status=_tr("Error: Invalid recipient!");
					break;
				}
				if(!$is_intra_fb && 
					(
						!isset($_POST['btc_addr']) || 
						strlen($_POST['btc_addr'])<27 || 
						strlen($_POST['btc_addr'])>34
					)
				){
					$post_status=_tr("Error: Invalid Bitcoin Address!");
					break;
				}
				if(!isset($_POST['amount'])){
					$post_status=_tr("Error: Must specify an amount!");
					break;
				}
				if(!is_numeric($_POST['amount'])){
					$post_status=_tr("Error: Invalid send amount!");
					break;
				}
				$amount=$_POST['amount']*100000000;
				if((string)round($amount)!==(string)($amount)){
					$post_status=_tr("Error: Send amount must be at least 1 satoshi (1/100000000 BTC)");
					break;
				}
				if($amount<=0){
					$post_status=_tr("Error: Invalid send amount!");
					break;
				}
				if($amount+($is_intra_fb ? $fb_tx_fee : $btc_tx_fee)>$balance){
					$post_status=_tr("Error: Insufficient Funds for Transfer");
					break;
				}
				$timestamp=date('Y-m-d H:i:s');
				$btc_tx_query=$db->prepare('insert into `tx` (`fb_id`,`type`,`btc_addr`,`fb_recip`,`date`,`amount`,`hash`) values (?, ?, ?, ?, ?, ?, ?)');
				if($is_intra_fb){
					$btc_tx_query->execute(array($user,'2',null,$_POST['fb_recip'],$timestamp,$amount,null));
					$btc_tx_query->execute(array($user,'2',null,'fee',$timestamp,$fb_tx_fee,null));
					$btc_tx_query->execute(array($_POST['fb_recip'],'1',null,$user,$timestamp,$amount,null));
					$post_status=_tr("Bitcoins sent successfully!");
					
					//notify recipient of the transaction!
					try{
						$app_token = file_get_contents("https://graph.facebook.com/oauth/access_token?" .
							"client_id=" . $fb_settings['appId'] .
							"&client_secret=" . $fb_settings['secret'] .
							"&grant_type=client_credentials");
						$app_token = str_replace("access_token=", "", $app_token);
						$notification_response=$facebook->api('/'.$_POST['fb_recip'].'/notifications', 'post', array(
							'href'=> '',
							'access_token'=> $app_token,
							'template'=> '@['.$user.'] has just sent you '.$_POST['amount'].' BTC!'
						));
					}catch(Exception $e){
						$post_status.=' '._tr('This user has not installed this app and will not be notified automatically. Please send them a message so they can access their Bitcoins.').' '.'<a href="javascript:" class="request-user" data-recip="'.$_POST['fb_recip'].'" data-amount="'.$_POST['amount'].'">'._tr('Send Request to Install App').'</a> or <a href="javascript:" class="post-to-recip-wall" data-recip="'.$_POST['fb_recip'].'" data-amount="'.$_POST['amount'].'">'._tr('Post to Their Wall').'</a>';
					}
				}else{
					$response=file_get_contents('https://blockchain.info/merchant/'.urlencode($blockchain_guid).'/payment?password='.urlencode($blockchain_pw).'&to='.urlencode($_POST['btc_addr']).'&amount='.urlencode($amount));
					if($response===false){
						$_POST['error-message']=$post_status=_tr('Error Connecting to Blockchain.info! Please try again later.');
						record_error(serialize($_POST));
					}else{
						$json_feed = json_decode($response);
						if(property_exists($json_feed,'error')){
							$_POST['error-message']=$post_status=$json_feed->error;
							record_error(serialize($_POST));
						}else{
							$btc_tx_query->execute(array($user,'2',$_POST['btc_addr'],null,$timestamp,$amount,$json_feed->tx_hash));
							$btc_tx_query->execute(array($user,'2',null,'fee',$timestamp,$btc_tx_fee,null));
							$post_status='<a href="https://blockchain.info/tx/'.urlencode($json_feed->tx_hash).'" target="_blank">'.$json_feed->message.'</a>';
						}
					}
				}
				load_tx();
				break;
		}
	}
}

?>
<!DOCTYPE html>
<html xmlns:fb="http://www.facebook.com/2008/fbml">
	<head>
		<meta charset="utf-8" />
		<title>Bitcoin Transactions</title>
		<link rel="stylesheet" href="stylesheets/reset.css" type="text/css" />
		<link rel="stylesheet" href="stylesheets/btc.css" type="text/css" />
		<link rel="stylesheet" href="javascript/chosen.css" type="text/css" />
		<script>var appurl='<?=$app_url?>',
					hosturl='<?=$callback_url?>',
					rates=<?=$rates?>;</script>
		<script type="text/javascript" src="/javascript/jquery-1.7.1.min.js"></script>
		<script type="text/javascript" src="/javascript/chosen.jquery.min.js"></script>
		<script type="text/javascript" src="/javascript/btc.js"></script>
	</head>
	<body>
		<!-- <? echo 'trans/'.$user_profile['locale'].'.txt'; ?> -->
		<div id="container">
		<?php if($user): ?>
			<div id="balance" class="clearfix">
				<span class="currency_foreign">
					<span id="balance_foreign"></span>
					<select id="foreign_balance_sel"></select>
				</span>
				<h2><?=_tr('Your Balance:')?></h2>
				<span id="balance_btc">฿<?=$balance/100000000?></span>
			</div>
			<div id="operations" class="clearfix">
				<? if(isset($post_status)): ?>
				<div id="form_status"><?=$post_status?></div>
				<? endif; ?>
				<form id="add_bitcoins" class="operation clearfix" action="/?sid=<?=htmlspecialchars(session_id())?>" method="POST">
					<h3><?=_tr('Fund Your Account')?></h3>
					<img src="https://blockchain.info/qr?data=<?=$btc_addr?>&size=90" alt="<?=_tr('QR Code to send bitcoins to fund your account')?>" width="90" height="90" />
					<p><?=_tr('Add Bitcoins to your account using the following address:')?></p>
					<p class="address"><?=$btc_addr?></p>
					<p class="buy-some"><?=_tr('Need to buy Bitcoins?')?> <a href="https://blockchain.info" target="_blank">blockchain.info</a></p>
					<input type="hidden" name="action" value="new_send_addr" />
					<button type="submit"><?=_tr('Generate new Address')?></button>
				</form>
				<form id="send_to_fb" class="operation" action="/?sid=<?=htmlspecialchars(session_id())?>" method="POST">
					<h3><?=_tr('Send Bitcoins to a Facebook Friend')?></h3>
					<? if(count($friends['data'])===0): ?>
						<p class="no-friends"><?=_tr('You have no friends.')?></p>
					<? else: ?>
						<div class="field">
							<label for="fb_recip"><?=_tr('Recipient:')?></label>
							<select id="fb_recip" name="fb_recip">
							<? foreach($friends['data'] as $friend): ?>
								<option value="<?=$friend['id']?>"><?=$friend['name']?></option>
							<? endforeach; ?>
							</select>
						</div>
						<div class="field">
							<label for="fb_amount"><?=_tr('Amount:')?></label>
							<input id="fb_amount" name="amount" />
							<span class="suffix">BTC</span>
							<span class="note"><span class="foreign-val"></span>฿<?=$fb_tx_fee/100000000?> <?=_tr('Fee added to this transaction.')?></span>
						</div>
						<input type="hidden" name="action" value="send_to_fb" />
						<button type="submit"><?=_tr('Send')?></button>
					<? endif; ?>
				</form>
				<form id="send_to_btc" class="operation" action="/?sid=<?=htmlspecialchars(session_id())?>" method="POST">
					<h3><?=_tr('Send Bitcoins to a Bitcoin Address')?></h3>
					<div class="field">
						<label for="btc_recip"><?=_tr('Address:')?></label>
						<input id="btc_recip" name="btc_addr" />
					</div>
					<div class="field">
						<label for="btc_amount"><?=_tr('Amount:')?></label>
						<input id="btc_amount" name="amount" />
						<span class="suffix">BTC</span>
						<span class="note"><span class="foreign-val"></span>฿<?=$btc_tx_fee/100000000?> <?=_tr('Fee added to this transaction.')?></span>
					</div>
					<input type="hidden" name="action" value="send_to_btc" />
					<button type="submit"><?=_tr('Send')?></button>
				</form>
			</div>
			<div id="history">
				<h3><?=_tr('Transaction History')?></h3>
				<table id="tx">
					<thead>
						<th><?=_tr('Date')?></th>
						<th><?=_tr('Description')?></th>
						<th><?=_tr('Deposit')?></th>
						<th><?=_tr('Withdrawal')?></th>
						<th><?=_tr('Balance')?></th>
					</thead>
					<tbody>
					<? if(count($tx_data)===0): ?>
						<tr class="no-tx">
							<td colspan="5"><?=_tr('No Transactions')?></td>
						</tr>
					<? else: ?>
						<? $display_index=0;
						for($i=0;$i<$tx_count;++$i): 
							$tx_row=$ordered_tx[$i];
							if($tx_row['fb_recip']==='fee') continue;
							++$display_index;
							if($display_index-1<($display_page-1)*$tx_per_page || $display_index-1>=$display_page*$tx_per_page) continue;
							$is_intra_fb=$tx_row['fb_recip']!==NULL;
							$is_deposit=(int)$tx_row['type']===1 || (int)$tx_row['type']===4;
							$is_pending=(int)$tx_row['type']===4;
							$is_revert=$tx_row['hash']==='revert';
							$reverted_already=strtotime($tx_row['date'])<strtotime('-'.$revert_duration);
						?>
							<tr class="<?=$is_intra_fb ? ($is_revert ? 'revert' : 'fb') : 'btc'?>">
								<td class="date"><?=$tx_row['date']?></td>
								<td class="desc">
									<?
										if(!$is_deposit && !$reverted_already){
											$friend_activated=user_activated($tx_row['fb_recip']);
											if($friend_activated===false){
												echo '<span class="not-activated"><a href="javascript:" class="request-user" data-recip="'.$tx_row['fb_recip'].'" data-amount="'.($tx_row['amount']/100000000).'">'._tr('Send Request to Install App').'</a> or <a href="javascript:" class="post-to-recip-wall" data-recip="'.$tx_row['fb_recip'].'" data-amount="'.($tx_row['amount']/100000000).'">'._tr('Post to Their Wall').'</a></span>';
											}
										}
									?>
									<span class="qualifier">
										<?=$is_deposit ? _tr('Deposit from:') : _tr('Withdrawal to:')?>
										<?=$is_revert ? ' ('._tr('Reverted').')' : ''?>
										<?=$is_pending ? ' ('._tr('Pending').')' : ''?>
									</span>
									<span class="foreign_id">
										<? if($is_intra_fb){
											$friend_info = json_decode(file_get_contents('http://graph.facebook.com/'.$tx_row['fb_recip']));
											
											if(!$is_revert){
												echo '<img src="https://graph.facebook.com/'.$tx_row['fb_recip'].'/picture?type=square" alt="'.$friend_info->name.'">';
											}
											
											echo $friend_info->name;
											
										}else{
											echo $tx_row['btc_addr'];
										}
									?></span>
								</td>
								<td class="deposit"><?=$is_deposit ? $tx_row['amount']/100000000 : ''?></td>
								<td class="withdrawl"><?=!$is_deposit ? $tx_row['amount']/100000000 : ''?></td>
								<td class="balance"><?=$is_pending ? '' : $tx_row['balance']/100000000?></td>
							</tr>
						<? endfor; ?>
					<? endif; ?>
					</tbody>
				</table>
				<? if($last_page>1): ?>
					<ul id="pager">
					<? if($display_page*$tx_per_page<$display_count): ?>
						<li class="previous"><a href="<?=$callback_url?>?p=<?=$display_page+1?>"><?=_tr('Older')?> &rarr;</a></li>
					<? endif; ?>
					<? if($display_page>1): ?>
						<li class="next"><a href="<?=$callback_url?>?p=<?=$display_page-1?>">&larr; <?=_tr('Newer')?></a></li>
					<? endif; ?>
					<? for($i=0;$i<$display_count;$i+=$tx_per_page): 
						$c_page=(int)($i/$tx_per_page)+1;
						if($last_page>10 && !($c_page===1 || $c_page===$last_page || abs($c_page-$display_page)<4)) continue;
					?>
						<? if($c_page===$display_page): ?>
						<li><?=$display_page?></li>
						<? else: ?>
						<li><a href="<?=$callback_url?>?p=<?=$c_page?>"><?=$c_page?></a></li>
						<? endif; ?>
					<? endfor; ?>
					</ul>
				<?endif;?>
			</div>
			<div id="footer">
				<h3><?=_tr('Want to show support?')?></h3>
				<div id="share">
					<a href="javascript:" id="share-wall" class="facebook-button">
						<span class="plus"><?=_tr('Post to Wall')?></span>
					</a>
					<a href="javascript:" id="share-msg" class="facebook-button speech-bubble">
						<span class="speech-bubble"><?=_tr('Send Message')?></span>
					</a>
					<a href="javascript:" id="share-request" class="facebook-button apprequests">
						<span class="apprequests"><?=_tr('Send Requests')?></span>
					</a>
				</div>
				<p><?=_tr('Send Bitcoins to ')?><em>1DUerWAepez56PziZTnSBzT2jFTF4722eK</em></p>
				<p><a href="translate.php"><?=_tr('Submit a translation of the interface')?></a></p>
				<p><a href="mailto:ben@salamanderphp.com"><?=_tr('Contact:')?> ben@salamanderphp.com</a></p>
			</div>
		<?php else: ?>
			<p>In order to use this application, you must be logged in and registered.<br />Click on the login button below and accept the message to begin using Bitcoin Transactions.</p>
			<fb:login-button></fb:login-button>
			<p><a href="mailto:ben@salamanderphp.com"><?=_tr('Contact:')?> ben@salamanderphp.com</a></p>
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
