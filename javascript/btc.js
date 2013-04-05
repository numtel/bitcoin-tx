jQuery(function($){
	var getCookie=function(c_name){
			var i,x,y,ARRcookies=document.cookie.split(";");
			for (i=0;i<ARRcookies.length;i++){
				x=ARRcookies[i].substr(0,ARRcookies[i].indexOf("="));
				y=ARRcookies[i].substr(ARRcookies[i].indexOf("=")+1);
				x=x.replace(/^\s+|\s+$/g,"");
				if (x==c_name){return unescape(y);}
			}
		},
		setCookie=function(c_name,value,exdays){
			var exdate=new Date();
			exdate.setDate(exdate.getDate() + exdays);
			var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());
			document.cookie=c_name + "=" + c_value;
		},
		isNumber=function(n){
			return !isNaN(parseFloat(n)) && isFinite(n);
		},
		foreignSel=document.getElementById('foreign_balance_sel'),
		foreignBal=document.getElementById('balance_foreign'),
		btcBal=document.getElementById('balance_btc')
		rateOptions="",
		cookieCurrency=getCookie('foreignCurrency');
	for(var i in rates){
		if(rates.hasOwnProperty(i)){
			rateOptions+='<option value="'+i+'">'+i+'</option>';
		}
	}
	$(foreignSel).html(rateOptions).on('change',function(e){
		foreignBal.innerHTML=Math.round(rates[this.value]['last']*btcBal.innerHTML.substr(1)*100)/100;
		setCookie('foreignCurrency',this.value,9000);
		$('input[name=amount]').trigger('change');
	});
	if(cookieCurrency!=null && cookieCurrency!="") $(foreignSel).val(cookieCurrency);
	$(foreignSel).trigger('change');


	$('#fb_recip').chosen();
	$('input[name=amount]').on('change keyup',function(e){
		var foreignVal=$(this).parent().find('.foreign-val');
		if(this.value=='' || this.value=='0'){
			foreignVal.html('');
		}else if(isNumber(this.value)){
			foreignVal.html((Math.round(rates[foreignSel.value]['last']*this.value*100)/100)+' '+foreignSel.value);
		}else{
			foreignVal.html('Invalid amount!');
		}
	});
	
	$('#share-wall').on('click',function(e){
		FB.ui({method: 'feed',link: appurl},function (response) {});
	});
	$('#share-msg').on('click',function(e){
		FB.ui({method: 'send',link: appurl},function (response) {});
	});
	$('#share-request').on('click',function(e){
		FB.ui({method: 'apprequests',message: 'Send Bitcoins to your friends!'},function (response) {});
	});
	
	$('#request-recip').on('click',function(e){
		var link=$(this),
			amount=link.attr('data-amount'),
			recipient=link.attr('data-recip');
		FB.ui({method: 'apprequests',
			message: 'I sent you '+amount+' BTC but you need to install this App to receive it.',
			to: recipient
		}, function (response) {});
  	});

});
