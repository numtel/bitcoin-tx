<?php
//error_reporting(E_ALL);
//ini_set("display_errors", 1);

require 'settings.php';

require 'inc/btc.php';
require 'inc/app.php';

require 'inc/init.php';

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
					rates=<?=get_x_rates()?>;</script>
		<script type="text/javascript" src="javascript/jquery-1.7.1.min.js"></script>
		<script type="text/javascript" src="javascript/chosen.jquery.min.js"></script>
		<script type="text/javascript" src="javascript/btc.js"></script>
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
				<span id="balance_btc">฿<?=satoshi_to_btc($balance)?></span>
			</div>
			<div id="operations" class="clearfix">
				<? if(isset($post_status)): ?>
				<div id="form_status"><?=$post_status?></div>
				<? endif; ?>
				<form id="add_bitcoins" class="operation clearfix" action="<?=$callback_url?>?sid=<?=htmlspecialchars(session_id())?>" method="POST">
					<h3><?=_tr('Fund Your Account')?></h3>
					<img src="https://blockchain.info/qr?data=<?=$btc_addr?>&size=90" alt="<?=_tr('QR Code to send bitcoins to fund your account')?>" width="90" height="90" />
					<p><?=_tr('Add Bitcoins to your account using the following address:')?></p>
					<p class="address"><?=$btc_addr?></p>
					<p class="buy-some"><?=_tr('Need to buy Bitcoins?')?> <a href="https://blockchain.info" target="_blank">blockchain.info</a></p>
					<input type="hidden" name="action" value="new_send_addr" />
					<button type="submit"><?=_tr('Generate new Address')?></button>
				</form>
				<form id="send_to_fb" class="operation" action="<?=$callback_url?>?sid=<?=htmlspecialchars(session_id())?>" method="POST">
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
							<span class="note"><span class="foreign-val"></span>฿<?=satoshi_to_btc($fb_tx_fee)?> <?=_tr('Fee added to this transaction.')?></span>
						</div>
						<input type="hidden" name="action" value="send_to_fb" />
						<button type="submit"><?=_tr('Send')?></button>
					<? endif; ?>
				</form>
				<form id="send_to_btc" class="operation" action="<?=$callback_url?>?sid=<?=htmlspecialchars(session_id())?>" method="POST">
					<h3><?=_tr('Send Bitcoins to a Bitcoin Address')?></h3>
					<div class="field">
						<label for="btc_recip"><?=_tr('Address:')?></label>
						<input id="btc_recip" name="btc_addr" />
					</div>
					<div class="field">
						<label for="btc_amount"><?=_tr('Amount:')?></label>
						<input id="btc_amount" name="amount" />
						<span class="suffix">BTC</span>
						<span class="note"><span class="foreign-val"></span>฿<?=satoshi_to_btc($btc_tx_fee)?> <?=_tr('Fee added to this transaction.')?></span>
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
										if($is_intra_fb && !$is_deposit && !$reverted_already){
											$friend_activated=user_activated($tx_row['fb_recip']);
											if($friend_activated===false){
												echo '<span class="not-activated"><a href="javascript:" class="request-user" data-recip="'.$tx_row['fb_recip'].'" data-amount="'.satoshi_to_btc($tx_row['amount']).'">'._tr('Send Request to Install App').'</a> or <a href="javascript:" class="post-to-recip-wall" data-recip="'.$tx_row['fb_recip'].'" data-amount="'.satoshi_to_btc($tx_row['amount']).'">'._tr('Post to Their Wall').'</a></span>';
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
								<td class="deposit"><?=$is_deposit ? satoshi_to_btc($tx_row['amount']) : ''?></td>
								<td class="withdrawl"><?=!$is_deposit ? satoshi_to_btc($tx_row['amount']) : ''?></td>
								<td class="balance"><?=$is_pending ? '' : satoshi_to_btc($tx_row['balance'])?></td>
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
				<p><a href="mailto:ben@salamanderphp.com"><?=_tr('Contact:')?> ben@salamanderphp.com</a></p>
				<p><?=_tr('Send Bitcoins to ')?><em>1DUerWAepez56PziZTnSBzT2jFTF4722eK</em></p>
				<p><strong><?=_tr('Without your donations, this app may shut down due to database costs.')?></strong></p>
				<p><a href="translate.php"><?=_tr('Submit a translation of the interface')?></a></p>
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
