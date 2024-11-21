<?php

if (!defined('_PS_VERSION_')) {
	exit;
}

class Ps_Product_Images_Email extends Module
{
	public function __construct()
	{
		$this->name = 'ps_product_images_email';
		$this->tab = 'emailing';
		$this->version = '1.0.0';
		$this->author = 'Miroslav Bendík';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Product Images in Order Emails');
		$this->description = $this->l('Adds product images to order confirmation emails.');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
	}

	public function install()
	{
		return parent::install() && $this->registerHook('displayProductImageInEmail') && $this->backupAndOverrideTemplate() && $this->registerHook('actionEmailSendBefore');
	}

	public function uninstall()
	{
		$this->restoreBackupTemplate();
		return parent::uninstall();
	}

	public function hookDisplayProductImageInEmail($params)
	{
		if (isset($params['product'])) {
			$product = $params['product'];
			$productId = (int)$product['id_product'];
			$image = Image::getCover($productId);
			if ($image) {
				$id_lang = (int)$this->context->language->id;
				$productInstance = new Product($product['id_product'], false, $id_lang);
				$image = Image::getCover($productInstance->id);
				$image_url = $this->context->link->getImageLink($productInstance->link_rewrite, $image['id_image'], 'home_default');

				$this->context->smarty->assign('product_image_url', $image_url);

				foreach ($product['customization'] as $customization) {
					$value = $customization['customization_text'];
					$pattern = '/.*<img src="https:\/\/pitchprint.io\/previews\/([a-f0-9]+)_1.jpg".*/';
					preg_match($pattern, $value, $matches);
					if (count($matches) > 1) {
						$pp_customization_project_id = $matches[1];
						$this->context->smarty->assign('pp_customization_project_id', $pp_customization_project_id);
					}
				}

				return $this->display(__FILE__, 'views/templates/hook/email_product_image.tpl');
			}
			return '';
		}
		return '';
	}


	private function backupAndOverrideTemplate()
	{
		$rootMailTemplatePath = _PS_MAIL_DIR_ . '_partials/order_conf_product_list.tpl';
		$backupTemplatePath = _PS_MAIL_DIR_ . '_partials/order_conf_product_list.tpl.bak';
		$moduleTemplatePath = $this->getLocalPath() . 'views/templates/mails/_partials/order_conf_product_list.tpl';

		// Check if the original template exists and hasn't already been backed up
		if (file_exists($rootMailTemplatePath) && !file_exists($backupTemplatePath)) {
			// Make a backup of the existing template
			if (!copy($rootMailTemplatePath, $backupTemplatePath)) {
				return false;
			}
		}

		// Copy the new template from the module to the root mails directory
		return copy($moduleTemplatePath, $rootMailTemplatePath);
	}

	private function restoreBackupTemplate()
	{
		$rootMailTemplatePath = _PS_MAIL_DIR_ . '_partials/order_conf_product_list.tpl';
		$backupTemplatePath = _PS_MAIL_DIR_ . '_partials/order_conf_product_list.tpl.bak';

		// If a backup exists, restore it
		if (file_exists($backupTemplatePath)) {
			// Restore the backup
			copy($backupTemplatePath, $rootMailTemplatePath);
			// Optionally, you can remove the backup file after restoring
			unlink($backupTemplatePath);
		}
	}

	public function hookActionEmailSendBefore($params)
	{
		// Check the template to override
		if ($params['template'] == 'order_conf') {
			$params['templatePath'] = _PS_MODULE_DIR_ . 'ps_product_images_email/mails/' . $this->context->language->iso_code . '/';
		}
	}

}
