<?php
	/*
	 By Uendel Silveira
	 Developer Web
	 IDE: PhpStorm
	 Created at: 27/10/25
	*/
	
	namespace Us\PaymentModuleManager\Gateways;
	
	class MercadoPagoStrategy
	{
		public function processPayment(float $amount): bool
		{
			// Logic to process payment with Mercado Pago
			echo "Processing payment of $" . $amount . " with Mercado Pago.\n";
			return true;
		}

	}