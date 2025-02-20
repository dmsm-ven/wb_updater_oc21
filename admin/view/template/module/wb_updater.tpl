<?php echo $header; ?><?php echo $column_left; ?>

<div id="content" class="ozon_seller_settings_admin_container">
	<h1>Настройка синхронизации цен с Seller Wildberries</h1>
	
	<form class="form-horizontal" action="<?php echo $action; ?>" id="updater_form" method="post">
		
		<div class="form-group">
			<label class="control-label col-sm-2" 
				style="display: flex; justify-content: end; align-items: start; margin-top: 0px; padding-top: 0px;" 
				for="is_active">Обновление по таймеру</label>
			<div class="col-sm-10"> 
				<select name="is_active">
					<option value="1" <?php echo ($is_active ? "selected" : ""); ?>>Активно</option>
					<option value="0" <?php echo ($is_active ? "" : "selected"); ?>>Выключено</option>
				</select>        
			</div>
		</div>

		<div class="form-group">
			<label class="control-label col-sm-2" for="api_key">API ключ Wildberries</label>
			<div class="col-sm-10">          
				<input value="<?php echo $api_key; ?>"
					type="text" 
					class="form-control" id="api_key"
					style="max-width: 300px;"
					placeholder="0000-0000-0000-0000" 
					name="api_key">
			</div>
		</div>
		
		<div class="form-group">
			<label class="control-label col-sm-2" for="discount_percent">Наценка, %</label>
			<div class="col-sm-10">
				<input value="<?php echo $general_discount; ?>"
						type="number" 
						style="max-width: 80px;"
						min="-100" 
						max="100" 
						class="form-control" 
						id="discount_percent" 
						placeholder="0" 
						name="general_discount">
			</div>
		</div>
 
		<div class="form-group">
			<label class="control-label col-sm-2">Дополнительная наценка по условию</label>
			<div class="col-sm-10 discount_step__box">
				<?php $i = 0; ?>
				<?php foreach($discount_steps as $step => $ratio) {?>
				<div class="discount_step__item">
					<div class="discount_step__min_border">
						<label for="step_key<?php echo $i;?>">Если цена менее (руб.)</label>
						<input type="number" step="10" value="<?php echo $step; ?>" class="form-control" name="step_key<?php echo $i;?>" id="step_key<?php echo $i;?>"></input>
					</div>
					<div class="discount_step__ratio">
						<label for="step_value<?php echo $i;?>">То коэффициент</label>
						<input type="number" step="0.1" value="<?php echo $ratio; ?>" class="form-control" name="step_value<?php echo $i;?>" id="step_value<?php echo $i;?>"></input>
					</div>
					<div class="discount_step__remove">
						<button type="button" class="btn btn-sm btn-danger btn_remove_step" data-for="step_key<?php echo $i;?>">
						<i class="fa fa-trash fa-fw"></i>
						Убрать
						</button>
					</div>
				</div>
				<?php $i++;} ?>
				<h3>Добавить новый коэффициент</h3>
				<div class="discount_step__item">
					<div class="discount_step__min_border">
						<label for="step_key<?php echo $i;?>">Если цена менее (руб.)</label>
						<input type="number" step="10" value="" class="form-control" name="step_key_new" id="step_key_new"></input>
					</div>
					<div class="discount_step__ratio">
						<label for="step_value<?php echo $i;?>">То коэффициент</label>
						<input type="number" step="0.1" value="" class="form-control" name="step_value_new" id="step_value_new"></input>
					</div>
					<div class="discount_step__add_new">
						<button type="submit" class="btn btn-sm btn-primary" id="btn_add_step">
							<i class="fa fa-plus fa-fw"></i>
							Добавить
						</button>
					</div>
				</div>
			</div>
		</div>

		<div class="form-group">
			  <label class="control-label col-sm-2">Остатки складов</label>
			  <div class="col-sm-10">
			  <fieldset>
			  <?php foreach($checked_stocks as $stock) { ?>
				<input type="checkbox" <?php echo $stock['checked'] ? "checked" : ""; ?> name="stock_num<?php echo $stock['stock_id']; ?>" value="<?php echo $stock['name']; ?>"><span><?php echo $stock['name']; ?></span><br> 
			  <?php }?>
			  </fieldset>
			  </div>
		  </div>
 
 		<div class="form-group">
			<label class="control-label col-sm-2" for="last_update">Результат недавних выполнений</label>
			<div class="col-sm-10">          
				<textarea style="min-height: 120px; width: 75%;"><?php echo $last_update_lines; ?></textarea>
			</div>
		</div>
 
		<div class="form-group">
				<div class="text-center">
				<p>Наценка выгрузки в Seller Wildberries, базовая цена расчитывается от цены товара с сайта</p>
				<p><?php echo $discount_steps_description; ?></p>
				<p><?php echo $cron_timer_description; ?></p>
				<p>Файл для отладки в формате JSON <a href="<?php echo $feed_uri; ?>" style="font-weight: bold;" target="_blank">Ссылка</a></p>
			</div>
		</div>
		
		<div class="form-group">        
			<div class="text-center">
				<button id="save_settings_btn" type="submit" class="btn btn-default btn-success">Сохранить настройки</button>
				<button id="run_now_command" type="button" class="btn btn-default btn-primary">Выполнить обновление сейчас</button>
				<div class="progress" style="visibility: hidden; margin-top: 12px;" id="progress-bar-box">
					<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
				</div>
			</div>
    	</div>
	</form>
  
</div>

<script>
document.addEventListener("DOMContentLoaded", (event) => {
  	const runNowBtn = document.querySelector('#run_now_command');
  	const saveBtn = document.querySelector('#save_settings_btn');
  	const progressBarBox = document.querySelector('#progress-bar-box');
	
  	runNowBtn.addEventListener('click', () => {
	  runNowBtn.setAttribute('disabled', true);
	  saveBtn.setAttribute('disabled', true);

	  progressBarBox.style.visibility = 'visible';
  
	  fetch(<?php echo "'", html_entity_decode($update_now_uri), "'"; ?>, {
		  method: "POST"
		}).then((res) => {
			 window.location.reload();
		})
		.catch(function(res){ 
			console.log('ERROR: ' + res);
			progressBarBox.style.visibility = 'collapse';
		});
	});

	document.querySelectorAll('.btn_remove_step').forEach((btn) => 
	{	
		btn.addEventListener('click', async () => {
			btn.disabled = true;
			const stepKeyValue = parseInt(document.querySelector('#' + btn.dataset.for).value);

			const res = await fetch('<?php echo html_entity_decode($remove_step_uri); ?>&key_to_delete=' + stepKeyValue, {
				method: "POST"
			});
			
			window.location.reload();
						
		});
	});
});
</script>

<?php echo $footer ?>