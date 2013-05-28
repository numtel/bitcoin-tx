<?

function satoshi_to_btc($amount){
	return $amount/100000000;
}

function btc_to_satoshi($amount){
	return $amount*100000000;
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

//returns btc to fiat exchange rates json string
function get_x_rates(){
	global $db;
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
	return $rates;
}
