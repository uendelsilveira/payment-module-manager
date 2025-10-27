<?php
	
	namespace Us\PaymentModuleManager\Providers;
	
	use Illuminate\Support\ServiceProvider;
	use Us\PaymentModuleManager\Services\MercadoPagoClient;
	
	class PaymentServiceProvider extends ServiceProvider
	{
		public function register()
		{
			$this->mergeConfigFrom(__DIR__.'/../config/payment.php', 'payment');
			
			$this->app->singleton('payment', function ($app) {
				$provider = config('payment.default');
				
				return match($provider) {
					'mercadopago' => new MercadoPagoClient(),
					default => null,
				};
			});
		}
		
		public function boot()
		{
			$this->publishes([
				__DIR__.'../config/payment.php' => $this->config_path('payment.php'),
			], 'config');
		}
		
		private function config_path($string)
		{
			return $this->app()->basePath().'/config/'.$string;

		}
	}
