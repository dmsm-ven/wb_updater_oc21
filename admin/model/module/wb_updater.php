<?php
class ModelModuleWbUpdater extends Model {
	
	public function getProducts($checked_stock_ids){
		
		if(!$checked_stock_ids){
			return [];
		}
		
		$sql = "SELECT CONCAT('PSM-', p.product_id) as shopSku, 
					ROUND(p.price, 0) as price, 
					SUM(IFNULL(pts.quantity, 0)) as quantity				
				FROM oc_product p 
					JOIN oc_product_description pd ON (p.product_id = pd.product_id) 
					LEFT JOIN oc_product_to_stock pts ON (p.product_id = pts.product_id)
				WHERE p.status = 1 AND 
					p.price > 0 AND
					FIND_IN_SET(pts.stock_id, '" . $checked_stock_ids . "') > 0						
				GROUP BY p.product_id
				ORDER BY p.sku";
		$query = $this->db->query($sql);
        return $query->rows;
	}
	
	public function LogUpdateResult($message, $result = 1, $date_start, $date_end){
		
		if(!$date_start && !$date_end){
			$date_start = $date_end = date('Y-m-d H:i:s');
		}
		
		$sql = "INSERT INTO `wb_updater_log`(`result`, `date_start`, `date_end`, `message`) 
									 VALUES (" . (int)$result . ", '" . $date_start . "', '" . $date_end . "', '" . $message . "')";
		$this->db->query($sql);
	}
		
	public function GetLastLogs($takeCount = 100){
		$query = $this->db->query('SELECT * FROM wb_updater_log ORDER BY update_id DESC LIMIT ' . (int)$takeCount);
        return $query->rows;
	}	
}