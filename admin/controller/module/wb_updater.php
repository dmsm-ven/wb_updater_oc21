<?php
class ControllerModuleWbUpdater extends Controller {	
	private $api_client;
	
	public function index() {
		$this->document->setTitle('Интеграция сайта с seller.wildberries.ru');		
		$this->load->model('module/wb_updater');		
		$this->load->model('setting/setting');	
		$this->load->model('module/product_stock');		
		
		$allStocks = $this->model_module_product_stock->getStocks();
		
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			 //Сохранение настроек на странице админки

			 if (isset($this->request->post['is_active'])) {
				$this->model_setting_setting->editSettingValue('wb_updater', 'wb_updater_is_active', (bool)$this->request->post['is_active']);
			}
			
			 if (isset($this->request->post['general_discount'])) {
				 $this->model_setting_setting->editSettingValue('wb_updater', 'wb_updater_general_discount', $this->request->post['general_discount']);
			 }

			 if (isset($this->request->post['api_key'])) {
				 $this->model_setting_setting->editSettingValue('wb_updater', 'wb_updater_api_key', $this->request->post['api_key']);
			 }
			 
			$checkedStocksIdsToSave = [];
			foreach($allStocks as $stock){
				if((bool)$this->request->post['stock_num' . $stock['stock_id']]){
					$checkedStocksIdsToSave[] =  $stock['stock_id'];
				}
			}
			$checkedStocksIdsToSaveString = !empty($checkedStocksIdsToSave) ? implode(',', $checkedStocksIdsToSave) : '';
			$this->model_setting_setting->editSettingValue('wb_updater', 'wb_updater_checked_stocks_ids' , $checkedStocksIdsToSaveString);

			$this->response->redirect($this->url->link('module/wb_updater', 'token=' . $this->session->data['token'], 'SSL'));
		}
					
		$settings = $this->model_setting_setting->getSetting('wb_updater');
		
		$checkedStockIds = isset($settings['wb_updater_checked_stocks_ids']) ? explode(',', $settings['wb_updater_checked_stocks_ids']) : [];
			
		$data['checked_stocks'] = [];
		foreach($allStocks as $stock){
			$stock['checked'] = in_array($stock['stock_id'], $checkedStockIds);
			$data['checked_stocks'][] = $stock;
		}
		
		//Сохранить
		$data['action'] = $this->url->link('module/wb_updater', 'token=' . $this->session->data['token'] . $url, 'SSL');
				
		//API ключ WB, переодически должен менятся
		$data['api_key'] = isset($settings['wb_updater_api_key']) ? $settings['wb_updater_api_key'] : '0000-0000-0000-0000';
		
		//Наценка от цены сайта
		$data['general_discount'] = isset($settings['wb_updater_general_discount']) ? $settings['wb_updater_general_discount'] : '0';

		//Обновление по таймеру включено/выключено
		$data['is_active'] = isset($settings['wb_updater_is_active']) ? (bool)$settings['wb_updater_is_active'] : false;
		
		
		$logs = $this->model_module_wb_updater->GetLastLogs();
		$logsRows = [];
		if($logs){
			foreach($logs as $logRow){			
				$logString = ((bool)$logRow['result'] ? '✅' : '🟥') . ' ' . $logRow['date_start']. ' - ' . $logRow['date_end'] . ' | ' . $logRow['message'];
				$logsRows[] = $logString;
			}
		}
		
		$data['last_update_lines'] = !empty($logsRows) ? implode("\n", $logsRows) : 'Нет данных';
				
		$data['action'] = $this->url->link('module/wb_updater', 'token=' . $this->session->data['token'], 'SSL');
		$data['feed_uri'] = $this->url->link('module/wb_updater/feed', 'token=' . $this->session->data['token'], 'SSL');
		$data['update_now_uri'] = $this->url->link('module/wb_updater/update_now', 'token=' . $this->session->data['token'], 'SSL');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('module/wb_updater.tpl', $data));
	}
	
	//JSON фид который нигде не используется, - нужен просто что бы примерно видеть какие данные выгружаются через API
	public function feed(){
		$this->load->model('module/wb_updater');
		$this->load->model('setting/setting');		
		
		$settings = $this->model_setting_setting->getSetting('wb_updater');
		$checked_stock_ids = isset($settings['wb_updater_checked_stocks_ids']) ? $settings['wb_updater_checked_stocks_ids'] : '0';
		$discount = $settings['wb_updater_general_discount'];
		
		$products = $this->model_module_wb_updater->getProducts($checked_stock_ids);
		$this->AppendDiscounts($products, $discount);
			
		// $api_key = $settings['wb_updater_api_key'];
		// $this->api_client = new WbApiClient($api_key, $this->model_module_wb_updater);
		// try{
		// $wbPidMaps = $this->api_client->ReceiveWbProductsData();
		// }catch(Exception $e){
			// header('Content-Type: text/plain');
			// echo 'Ошибка запроса к API Wildberries: ' . $e->getMessage() . '\r\n';
		// }
		
		$wbProducts = [];
		foreach($products as $product){
			$wbProduct = $product;
			
			//$wbProduct['wb_barcode'] = array_key_exists($product['shopSku'], $wbPidMaps['barcode_map']) ? 
				//$wbPidMaps['barcode_map'][$product['shopSku']] : 'Не добавлен в личном кабинете';
			
			//$wbProduct['nmid_map'] = array_key_exists($product['shopSku'], $wbPidMaps['nmid_map']) ? 
				//$wbPidMaps['nmid_map'][$product['shopSku']] : 'Не добавлен в личном кабинете';
			
			$wbProducts[] = $wbProduct;
		}
				
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($wbProducts);
	}
	
	public function update_now(){
		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			return;
		}
				
		$this->load->model('setting/setting');
		$this->load->model('module/wb_updater');
		
		$settings = $this->model_setting_setting->getSetting('wb_updater');

		$isActive = (bool)$settings['wb_updater_is_active'];

		 if(!$isActive){
		 	return;
		 }

		$api_key = $settings['wb_updater_api_key'];
		$discount = $settings['wb_updater_general_discount'];
		$checked_stock_ids = $settings['wb_updater_checked_stocks_ids'];
			
		$result = false;
		$date_start = date('Y-m-d H:i:s');
		$message = 'Неизвестная ошибка';
		
		try {
			$updatedProducts = $this->ExecuteUpdate($api_key, $discount, $checked_stock_ids);
			$result = true;
			if($updatedProducts > 0){
				$message = "ОК - обновлено [{$updatedProducts}] товаров";
			} else if($updatedProducts === 0){
				$message = "ОК - все остатки обнулены";
			}
		} catch (Exception $e) {
			$message = $e->getMessage();
		}
		
		$date_end = date('Y-m-d H:i:s');		
		$this->model_module_wb_updater->LogUpdateResult($message, $result, $date_start, $date_end);				
	}
	
	private function ExecuteUpdate($api_key, $discount, $checked_stock_ids){
		if(!$api_key){
			throw new Exception('API ключ не был предоставлен');
		}
		if(empty($discount)){
			throw new Exception('Наценка не была предоставлена');
		}
		$this->load->model('module/wb_updater');
		
		$this->api_client = new WbApiClient($api_key, $this->model_module_wb_updater);
			
		$products = $this->model_module_wb_updater->getProducts($checked_stock_ids);
				
		$this->AppendDiscounts($products, $discount);
			
		//[0] => barcode_map, [1] => nmid_map
		$wbPidMaps = $this->api_client->ReceiveWbProductsData();		
		//$this->model_module_wb_updater->LogUpdateResult('TEST 1: barcode_map=' . count($wbPidMaps['barcode_map']). ' | nmid_map=' . count($wbPidMaps['nmid_map']));
			
		//Код склада куда будем загружать остатки
		$warehouseId = $this->api_client->GetWarehouseId();
		//$this->model_module_wb_updater->LogUpdateResult('TEST 2: warehouseId=' . $warehouseId);
		
		//Очищаем остатки на складе
		$this->api_client->ClearStock($warehouseId, $wbPidMaps['barcode_map']);
		//$this->model_module_wb_updater->LogUpdateResult('TEST 3: ClearStock OK');
		
		if($products){	
			//[0] => price_map, [1] => quantity_map
			$etkPidMaps = $this->GetProductMaps($products);
			//$this->model_module_wb_updater->LogUpdateResult('TEST 4: price_map=' . count($etkPidMaps['price_map']). ' | quantity_map=' . count($etkPidMaps['quantity_map']));
			
			//Обновляем остатки
			$this->api_client->UpdateQuantity($warehouseId, $wbPidMaps['barcode_map'], $etkPidMaps['quantity_map'], $etkPidMaps['price_map']);
			//$this->model_module_wb_updater->LogUpdateResult('TEST 5: UpdateQuantity OK');
			
			//Обновляем цены
			$this->api_client->UpdatePrices($productsData, $wbPidMaps['nmid_map'], $etkPidMaps['price_map']);
			//$this->model_module_wb_updater->LogUpdateResult('TEST 6: UpdatePrice OK');
			
			return count($wbPidMaps['nmid_map']);
		}
		
		return 0;
	}
	
	//Получаем детали товаров и компонуем в словарь
	private function GetProductMaps($products){
		$pidToPriceMap = [];
		$pidToQuantityMap = [];
			
		foreach($products as $product){
			$pidToPriceMap[$product['shopSku']] = $product['price_with_discount'];
			$pidToQuantityMap[$product['shopSku']] = $product['quantity'];
		}
		
		
		return [
			'price_map' => $pidToPriceMap,
			'quantity_map' => $pidToQuantityMap
		];
	}
	
	//Применяем наценки и скидки на товары 
	private function AppendDiscounts(&$products, $discount){
		if(!$products){
			return;
		}
		
		$priceSteps = [
			'500' => '2.5',  //Если менее 500 руб, то * цену на 2.5
			'1000' => '2.0' //Если менее 1000 руб, то * цену на 2		
		];
		
		$i = 0;
		foreach($products as &$product){		
			$resultPrice = $product['price'];
			
			//1. Добавляем наценку на минимальную стоимость
			if($resultPrice < 1000){
				foreach($priceSteps as $step_price => $ratio){
					if($resultPrice < $step_price){
						
						$resultPrice = $resultPrice * $ratio;
						break;
					}	
				}
			}
			
			//2. Добавляем общую наценку, округляем до 10 руб.
			$resultPrice = ceil((float)$resultPrice * ((100.0 + (float)$discount) / 100.0) / 10) * 10;
			$product['price_with_discount'] = $resultPrice;
		}			
	}
		
}

class WbApiClient {
	private $api_key;
	private $logger;
	private $MAX_PRODUCTS_PER_PAGE_FOR_STOCK = 1000;
	private $SPAM_CHECK_TRY_COUNT = 5;
	
	public function __construct($api_key, &$logger) {
        $this->api_key = $api_key;
        $this->logger = $logger;
    }
		
	//Получаем от WB список номенклатуры которая добавлена через личный кабинет
	public function ReceiveWbProductsData(){		
		$cards = $this->GetAllWbCards();
				
		$pidToBarcodeMap = [];
		$pidToNMIDMap = [];
		
		
		foreach($cards as $card){
			$pidToBarcodeMap[$card->vendorCode] = $card->sizes[0]->skus[0]; // PSM-1 => 200004287123
			$pidToNMIDMap[$card->vendorCode] = $card->nmID; //PSM-1 => 1482332
		}
		
		
		return [
			'barcode_map' => $pidToBarcodeMap,
			'nmid_map' => $pidToNMIDMap
		];
		
	}
		
	//Получаем ID главного склада
	public function GetWarehouseId(){
		$API_METHOD_URI = "https://marketplace-api.wildberries.ru/api/v3/warehouses";

		$list = $this->callApi("GET", $API_METHOD_URI);

		if (!$list || count($list) == 0)
		{
			throw new Exception("Не найдено ни одного склада Wildberries");
		}
		if (count($list) > 1)
		{
			throw new Exception("Найден дополнительный склад WB, логика для обработки которого не реализована");
		}

		return $list[0]->id;
	}
		
	//Очищаем остатки у всех товаров на главном складе
	public function ClearStock($warehouseId, $barcode_map){
		$API_METHOD_URI = "https://marketplace-api.wildberries.ru/api/v3/stocks/" . $warehouseId;
		
		$currentPage = 0;
		$lastPage = (int)ceil((float)count($barcode_map) / $this->MAX_PRODUCTS_PER_PAGE_FOR_STOCK);

		do
		{
			$currentPageSkus = array_slice(
				array_values($barcode_map), 
				(int)$currentPage * $this->MAX_PRODUCTS_PER_PAGE_FOR_STOCK, 
				$this->MAX_PRODUCTS_PER_PAGE_FOR_STOCK
			);

			$payload = ['skus' => $currentPageSkus];

			try{			
			$response = $this->callApi("DELETE", $API_METHOD_URI, $payload);
			}catch(Exception $ex){
				//Пропускаем 404, т.к. она будет каждый вызов, т.к. мы очищаем сразу все остатки всех товаров
				if($ex->getMessage() !== 'Ошибка вызова API: HTTP/1.1 404 Not Found'){
					throw $ex;
				}
			}

			$currentPage++;
	
			if ($currentPage > $this->SPAM_CHECK_TRY_COUNT)
			{
				break;
			}
		} while ($currentPage < $lastPage);
	}
	
	private function GetAllWbCards(){	
		$API_METHOD_URI = "https://content-api.wildberries.ru/content/v2/get/cards/list?locale=ru";
        $PRODUCTS_PER_REQUEST = 100;
        $MAX_REQUEST_TRY_COUNT = 50; // <---- (5000 товаров), если больше то увеличить
		
		$list = [];
		$readedProducts = 0;
		$tryCount = 0;
		$lastNMID = 0;
		$lastUpdatedAt = '';
		
		$empty_payload = [
			'settings' => [
				'filter' => ['withPhoto' => -1], 
				'cursor' => ['limit' => $PRODUCTS_PER_REQUEST]
				]
		];
			
		do{
			if ($tryCount++ > $MAX_REQUEST_TRY_COUNT) // - на всякий случай делаем проверку, что бы не заспамить API, и не быть заблокированными
            {
				throw new Exception('Ошибка в работе метода WbApiClient_GetAllWbCards. Превышено максимальное количество запросов для ' . $API_URI);
            }
			
			$current_page_payload = $empty_payload;
			
			if(count($list) > 0){
				$payload_cursor = &$current_page_payload['settings']['cursor'];
				$payload_cursor['updatedAt'] = $lastUpdatedAt;
				$payload_cursor['nmID'] = (int)$lastNMID;
				
				//$this->logger->LogUpdateResult('current_page_payload: ' . json_encode($current_page_payload));
			}

			$apiResponse = $this->callApi('POST', $API_METHOD_URI, $current_page_payload);
			
			$lastUpdatedAt = $apiResponse->cursor->updatedAt;
			$lastNMID = (int)$apiResponse->cursor->nmID;
			$readedProducts = (int)$apiResponse->cursor->total;
												
			if(!$readedProducts){
				break;
			}
			
			$cards = $apiResponse->cards;
			
			foreach($cards as $card){
				$list[] = $card;
			}
			
			//$this->logger->LogUpdateResult('i=' . $tryCount . ' | count(list)=' . count($list) . ' | readedProducts=' . $readedProducts . ' | lastUpdatedAt=' . $lastUpdatedAt . ' | lastNMID=' . $lastNMID);
			
		}while($readedProducts == $PRODUCTS_PER_REQUEST);
		
		return $list;
	}
	
	private function callApi($method, $url, $data){
		$options = array(
			  'http' => array(
				'header' => "Content-type: application/json\r\nAuthorization: " . $this->api_key,
				'method' => $method,
				'content' => json_encode($data),
			  ),
			);

			$context = stream_context_create($options);
			if (($result = file_get_contents($url, false, $context)) === false) {
			  throw new Exception("Ошибка вызова API: " . $http_response_header[0]);
			}
			return json_decode($result);
	}
	
	//Обновляем остатки
	public function UpdateQuantity($warehouseId, $barcode_map, $quantity_map, $price_map){
		$API_METHOD_URI = "https://marketplace-api.wildberries.ru/api/v3/stocks/" . $warehouseId;
		
		$currentPage = 0;
		$lastPage = (int)ceil((float)count($barcode_map) / $this->MAX_PRODUCTS_PER_PAGE_FOR_STOCK);

		do
		{
			$stock_page_data = array_slice(
				$barcode_map, 
				$currentPage * $this->MAX_PRODUCTS_PER_PAGE_FOR_STOCK,
				$this->MAX_PRODUCTS_PER_PAGE_FOR_STOCK
			);
			
			$stock_page_items = [];
			foreach($stock_page_data as $shopSku => $barcode){			
				$hasQuantity = array_key_exists($shopSku, $quantity_map) && 
							   array_key_exists($shopSku, $price_map) && 
							   (int)$price_map[$shopSku] > 0;
							
				
				$stock_page_items[] = [
					'sku' => $barcode,
					'amount' => $hasQuantity ? (int)$quantity_map[$shopSku] : (int)0
				];
			}

			$payload = ['stocks' => $stock_page_items];
			
			//$this->logger->LogUpdateResult('PAYLOAD: ' . json_encode($payload));

			$response = $this->callApi("PUT", $API_METHOD_URI, $payload);


			$currentPage++;
			if ($currentPage > $this->SPAM_CHECK_TRY_COUNT)
			{
				break;
			}
		} while ($currentPage < $lastPage);
	}
	
	//Обновляем цены
	public function UpdatePrices($productsData, $nmid_map, $price_map){
		$API_METHOD_URI = "https://discounts-prices-api.wb.ru/api/v2/upload/task";

		$currentPage = 0;
		$lastPage = (int)ceil((float)count($nmid_map) / $this->MAX_PRODUCTS_PER_PAGE_FOR_STOCK);

		do
		{
			$productsPageData = array_slice($nmid_map,
				$currentPage * $this->MAX_PRODUCTS_PER_PAGE_FOR_STOCK,
				$this->MAX_PRODUCTS_PER_PAGE_FOR_STOCK
			);
			
			$productsPageItems = [];
			foreach($productsPageData as $shopSku => $nmid){
				$hasPrice = array_key_exists($shopSku, $price_map);
				
				$productsPageItems[] = [
					'nmID' => (int)$nmid,
					'price' => $hasPrice ? (int)$price_map[$shopSku] : (int)0
				];
			}
			
			$payload = ['data' => $productsPageItems];
			
			$response = $this->callApi("POST", $API_METHOD_URI, $payload);
			
			//$this->logger->LogUpdateResult('UPDATE PRICE RESPONSE: ' . json_encode($response));
			
			$currentPage++;

			//Убрать если товаров больше 5000
			if ($currentPage > $this->SPAM_CHECK_TRY_COUNT)
			{
				break;
			}
		} while ($currentPage < $lastPage);
	}
	
}
?>