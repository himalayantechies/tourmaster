<?php

	add_filter('goodlayers_plugin_payment_option', 'tourmaster_hblpay_payment_option');
	if( !function_exists('tourmaster_hblpay_payment_option') ){
		function tourmaster_hblpay_payment_option( $options ){

			$options['hblpay'] = array(
				'title' => esc_html__('hblpay', 'tourmaster'),
				'options' => array(
					'hblpay-live-mode' => array(
						'title' => __('HBL Pay Live Mode', 'tourmaster'),
						'type' => 'checkbox',
						'default' => 'disable',
					),
					'hblpay-merchantID' => array(
						'title' => esc_html__('Merchant ID', 'tourmaster'),
						'type' => 'text'
					),
					'hblpay-merchantKey' => array(
						'title' => esc_html__('Merchant Key', 'tourmaster'),
						'type' => 'text'
					),
					'hblpay-currency-code' => array(
						'title' => esc_html__('HBL Pay Currency Code', 'tourmaster'),
						'type' => 'text',	
						'default' => '524',
						'description' => esc_html__('524 for NPR and 840 for USD.', 'tourmaster')
					),
				)
			);

			return $options;
		} // tourmaster_hblpay_payment_option
	}

	add_filter('tourmaster_hblpay_button_atts', 'tourmaster_hblpay_button_attribute');
	if( !function_exists('tourmaster_hblpay_button_attribute') ){
		function tourmaster_hblpay_button_attribute( $attributes ){

			return array('method' => 'ajax', 'type' => 'hblpay');
		}
	}

	add_filter('goodlayers_hblpay_payment_form', 'tourmaster_hblpay_payment_form', 10, 2);
	if( !function_exists('tourmaster_hblpay_payment_form') ){
		function tourmaster_hblpay_payment_form( $ret = '', $tid = '' ){
			
			$live_mode = tourmaster_get_option('payment', 'hblpay-live-mode', 'enable');
			$merchantID = tourmaster_get_option('payment', 'hblpay-merchantID', '');
			$merchantKey = tourmaster_get_option('payment', 'hblpay-merchantKey', '');
			$currency_code = tourmaster_get_option('payment', 'hblpay-currency-code', '');

			$t_data = apply_filters('goodlayers_payment_get_transaction_data', array(), $tid, array('price', 'tour_id'));
			$price = floatval($t_data['price']['total-price']);
			$amount = get_formated_amount( $price );
            $invoiceNo = get_formated_invoiceNo( $tid );

			$productinfo = $t_data['tour_id'].'_'.date("ymd");

			$hash = process_hashvalue( $tid, $amount, $merchantID, $currency_code );

			ob_start();
			//exit;
?>
<div class="goodlayers-hblpay-redirecting-message" ><?php esc_html_e('Please wait while we redirect you to hblpay.', 'tourmaster') ?></div>
<form id="goodlayers-hblpay-redirection-form" method="post" action="<?php
		if( empty($live_mode) || $live_mode == 'disable' ){
			echo 'http://localhost/hbltest.php';
		}else{
			echo 'https://hblpgw.2c2p.com/HBLPGW/Payment/Payment/Payment';
		}
	?>" >
	<input type="hidden" name="paymentGatewayID" value="<?php echo esc_attr(trim($merchantID)); ?>" />
	<input type="hidden" name="invoiceNo" value="<?php echo esc_attr(trim($invoiceNo)); ?>" />
	<input type="hidden" name="productDesc" value="<?php echo esc_attr(trim($productinfo)); ?>" />
	<input type="hidden" name="amount" value="<?php	echo esc_attr(trim($amount));?>" />
	<input type="hidden" name="currencyCode" value="<?php echo esc_attr($currency_code); ?>" />
	<input type="hidden" name="hashValue" value="<?php echo esc_attr($hash); ?>" />
	<input type="hidden" name="return" value="<?php
		echo add_query_arg(array('tid' => $tid, 'step' => 4, 'payment_method' => 'hblpay', 'hblpay' => 1), tourmaster_get_template_url('payment'));
	?>" />
</form>
<script type="text/javascript">
	(function($){
		$('#goodlayers-hblpay-redirection-form').submit();
	})(jQuery);
</script>
<?php
			$ret = ob_get_contents();
			ob_end_clean();
			return $ret;		
		} // goodlayers_hblpay_payment_form
	}

	function get_formated_invoiceNo( $tid=null ){
		$invoice_prefix = "hbl";
		$invoiceNo = $invoice_prefix.$tid;
		if(strlen($invoiceNo) < 20){
			$formated_invoiceNo = str_pad((string) $invoiceNo, 20, '0', STR_PAD_LEFT);
			return $formated_invoiceNo;
		}
		else {
			return $invoiceNo;
		}
	}

	function get_formated_amount( $price=null ) {
		$amount = (int) ($price * 100);
		$formatedAmount = str_pad((string) $amount, 12, '0', STR_PAD_LEFT);
		return $formatedAmount;
	}

	function process_hashvalue( $tid=null, $amount=null, $merchantID=null, $currencyCode=null ){
		$invoiceNumber = get_formated_invoiceNo( $tid );
		$nonSecure = '';
		$signString = $merchantID.$invoiceNumber.$amount.$currencyCode.$nonSecure;
		$hashvalue = get_hashvalue($signString);
		return $hashvalue;
	}

	function get_hashvalue( $signatureString=null ){
		$SecretKey = tourmaster_get_option('payment', 'hblpay-merchantKey', '');
		$signData = hash_hmac('SHA256', $signatureString, $SecretKey, false);
		$signData = strtoupper($signData);
		return urlencode($signData);
	}
	function get_orderno_from_invoice( $invoiceNo=null ){
		$invoice_prefix = 'hbl';
		$tid = ltrim(ltrim($invoiceNo, '0'), $invoice_prefix);
		return $tid;
	}

	add_action('init', 'tourmaster_hblpay_process_ipn');
	if( !function_exists('tourmaster_hblpay_process_ipn') ){
		function tourmaster_hblpay_process_ipn(){
			if( isset($_GET['hblpay']) ){
				$payment_info = array(
					'payment_method' => 'hblpay'
				);
				if( isset($_REQUEST['paymentGatewayID']) && isset($_REQUEST['respCode']) && isset($_REQUEST['fraudCode']) && isset($_REQUEST['pan']) && isset($_REQUEST['amount']) && isset($_REQUEST['invoiceNo']) && isset($_REQUEST['tranRef']) && isset($_REQUEST['approvalCode']) && isset($_REQUEST['eci']) && isset($_REQUEST['dateTime']) && isset($_REQUEST['status']) && isset($_REQUEST['hashValue']) ) {

					$invoiceNo = !empty($_REQUEST['invoiceNo']) ? $_REQUEST['invoiceNo'] : '';
					$paymentGatewayID = !empty($_REQUEST['paymentGatewayID']) ? $_REQUEST['paymentGatewayID'] : '';
					$respCode = !empty($_REQUEST['respCode']) ? $_REQUEST['respCode'] : '';
					$fraudCode = !empty($_REQUEST['fraudCode']) ? $_REQUEST['fraudCode'] : '';
					$pan = !empty($_REQUEST['pan']) ? $_REQUEST['pan'] : '';
					$amount = !empty($_REQUEST['amount']) ? $_REQUEST['amount'] : '';
					$approvalCode = !empty($_REQUEST['approvalCode']) ? $_REQUEST['approvalCode'] : '';
					$eci = !empty($_REQUEST['eci']) ? $_REQUEST['eci'] : '';
					$tranRef = !empty($_REQUEST['tranRef']) ? $_REQUEST['tranRef'] : '';
					$dateTime = !empty($_REQUEST['dateTime']) ? $_REQUEST['dateTime'] : '';
					$status = !empty($_REQUEST['status']) ? $_REQUEST['status'] : '';
					$hashValue = !empty($_REQUEST['hashValue']) ? $_REQUEST['hashValue'] : '';

					$tid = get_orderno_from_invoice( $invoiceNo );
					
			        if ( !empty($tid) ){
			        	$tdata = tourmaster_get_booking_data(array('id'=>$tid), array('single'=>true));
			            $responseHash = $paymentGatewayID.$respCode.$fraudCode.$pan.$amount.$invoiceNo.$tranRef.$approvalCode.$eci.$dateTime.$status;
		            	$checkhash = get_hashvalue( $responseHash );
		            	$tamount = floatval((ltrim($amount, '0'))/100);
		            	$payment_info['transaction_id'] = $tranRef;
						$payment_info['invoice_no'] = $invoiceNo;
	        			$payment_info['amount'] = $tamount;
						$trans_authorised = false;

						if ($hashValue == $checkhash){
							$status = strtolower($status);
							if ( 'ap' == $status ){
								$trans_authorised = true;
		        				if ($tdata->order_status == 'pending') {
		        					$payment_info['success'] = "Transaction is successful.";
		        					$order_status = 'online-paid';
		        					tourmaster_update_booking_data( 
												array(
													'payment_info' => json_encode($payment_info),
													'payment_date' => current_time('mysql'),
													'order_status' => $order_status,
												),
									array('id' => $tid),
									array('%s', '%s', '%s'),
									array('%d')
									);
		        				}
							}
							elseif ('pe' == $status){
								$trans_authorised = true;
								$payment_info['notice'] = "HBL Pay transaction is pending.";  		
		        				$order_status = 'pending';
				        		tourmaster_update_booking_data( 
												array(
													'payment_info' => json_encode($payment_info),
													'payment_date' => current_time('mysql'),
													'order_status' => $order_status,
												),
								array('id' => $tid),
								array('%s', '%s', '%s'),
								array('%d')
								);
							}
							else{
								$trans_authorised = false;
								$error = "Transaction error.";
							}	
			            }
			            else{
			            	$trans_authorised = false;
							$error = "Security Error. Illegal access detected.";
			            }
			            if ( false == $trans_authorised) {
			            		$order_status = "rejected";
			            		$payment_info['error'] = $error;
								tourmaster_update_booking_data( 
												array(
													'payment_info' => json_encode($payment_info),
													'payment_date' => current_time('mysql'),
													'order_status' => $order_status,
												),
								array('id' => $tid),
								array('%s', '%s', '%s'),
								array('%d')
								);
						}
					}
					tourmaster_mail_notification('payment-made-mail', $tid);
					tourmaster_mail_notification('admin-online-payment-made-mail', $tid);
				}
				else{
					$error = "Invalid Request.";
				}
			}
			else{
				$error = "Invalid Request.";
			}
		}
	}
	// tourmaster_hblpay_process_ipn
?>