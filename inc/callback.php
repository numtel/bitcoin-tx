<?
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

