<?php


class Ps_EmailAlertsOverride extends Ps_EmailAlerts {
	public function hookActionValidateOrder($params) {
		if (!$this->merchant_order || empty($this->merchant_new_order_emails)) {
			return;
		}

		// Getting differents vars
		$context = Context::getContext();
		$id_lang = (int) $context->language->id;
		$locale = $context->language->getLocale();
		// We use use static method from current class to prevent retro compatibility issues with PrestaShop < 1.7.7
		$contextLocale = static::getContextLocale($context);

		$id_shop = (int) $context->shop->id;
		$currency = $params['currency'];
		$order = $params['order'];
		$customer = $params['customer'];
		$configuration = Configuration::getMultiple(
			[
				'PS_SHOP_EMAIL',
				'PS_MAIL_METHOD',
				'PS_MAIL_SERVER',
				'PS_MAIL_USER',
				'PS_MAIL_PASSWD',
				'PS_SHOP_NAME',
				'PS_MAIL_COLOR',
			], $id_lang, null, $id_shop
		);
		$delivery = new Address((int) $order->id_address_delivery);
		$invoice = new Address((int) $order->id_address_invoice);
		$order_date_text = Tools::displayDate($order->date_add);
		$carrier = new Carrier((int) $order->id_carrier);
		$message = $this->getAllMessages($order->id);

		if (!$message || empty($message)) {
			$message = $this->trans('No message', [], 'Modules.Emailalerts.Admin');
		}

		$items_table = '';

		$products = $params['order']->getProducts();
		$customized_datas = Product::getAllCustomizedDatas((int) $params['cart']->id);
		Product::addCustomizationPrice($products, $customized_datas);
		foreach ($products as $key => $product) {
			$unit_price = Product::getTaxCalculationMethod($customer->id) == PS_TAX_EXC ? $product['product_price'] : $product['product_price_wt'];

			$customization_text = '';
			if (isset($customized_datas[$product['product_id']][$product['product_attribute_id']][$order->id_address_delivery][$product['id_customization']])) {
				foreach ($customized_datas[$product['product_id']][$product['product_attribute_id']][$order->id_address_delivery][$product['id_customization']] as $customization) {
					if (isset($customization[Product::CUSTOMIZE_TEXTFIELD])) {
						foreach ($customization[Product::CUSTOMIZE_TEXTFIELD] as $text) {
							$customization_text .= $text['name'] . ': ' . $text['value'] . '<br />';
						}
						$customization_text .= '---<br />';
					}

					if (isset($customization[Product::CUSTOMIZE_FILE])) {
						$customization_text .= count($customization[Product::CUSTOMIZE_FILE]) . ' ' . $this->trans('image(s)', [], 'Modules.Emailalerts.Admin') . '<br />';
						$customization_text .= '---<br />';
					}
				}
				if (method_exists('Tools', 'rtrimString')) {
					$customization_text = Tools::rtrimString($customization_text, '---<br />');
				} else {
					$customization_text = preg_replace('/---<br \/>$/', '', $customization_text);
				}
			}

			$url = $context->link->getProductLink($product['product_id']);
			$items_table .=
				'<tr style="background-color:' . ($key % 2 ? '#DDE2E6' : '#EBECEE') . ';">
					<td style="padding:0.6em 0.4em;">' . $product['product_reference'] . '</td>
					<td style="padding:0.6em 0.4em;">
						<strong><a href="' . $url . '">' . $product['product_name'] . '</a>'
							. (isset($product['attributes_small']) ? ' ' . $product['attributes_small'] : '')
							. (!empty($customization_text) ? '<br />' . $customization_text : '')
						. '</strong>
					</td>
					<td style="padding:0.6em 0.4em; text-align:right;">' . $contextLocale->formatPrice($unit_price, $currency->iso_code) . '</td>
					<td style="padding:0.6em 0.4em; text-align:center;">' . (int) $product['product_quantity'] . '</td>
					<td style="padding:0.6em 0.4em; text-align:right;">'
						. $contextLocale->formatPrice($unit_price * $product['product_quantity'], $currency->iso_code)
					. '</td>
				</tr>';
		}
		foreach ($params['order']->getCartRules() as $discount) {
			$items_table .=
				'<tr style="background-color:#EBECEE;">
						<td colspan="4" style="padding:0.6em 0.4em; text-align:right;">' . $this->trans('Voucher code:', [], 'Modules.Emailalerts.Admin') . ' ' . $discount['name'] . '</td>
					<td style="padding:0.6em 0.4em; text-align:right;">-' . $contextLocale->formatPrice($discount['value'], $currency->iso_code) . '</td>
				</tr>';
		}
		if ($delivery->id_state) {
			$delivery_state = new State((int) $delivery->id_state);
		}
		if ($invoice->id_state) {
			$invoice_state = new State((int) $invoice->id_state);
		}

		if (Product::getTaxCalculationMethod($customer->id) == PS_TAX_EXC) {
			$total_products = $order->getTotalProductsWithoutTaxes();
		} else {
			$total_products = $order->getTotalProductsWithTaxes();
		}

		$order_state = $params['orderStatus'];

		// Filling-in vars for email
		$template_vars = [
			'{firstname}' => $customer->firstname,
			'{lastname}' => $customer->lastname,
			'{email}' => $customer->email,
			'{delivery_block_txt}' => MailAlert::getFormatedAddress($delivery, "\n"),
			'{invoice_block_txt}' => MailAlert::getFormatedAddress($invoice, "\n"),
			'{delivery_block_html}' => MailAlert::getFormatedAddress(
				$delivery, '<br />', [
					'firstname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
					'lastname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
				]
			),
			'{invoice_block_html}' => MailAlert::getFormatedAddress(
				$invoice, '<br />', [
					'firstname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
					'lastname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
				]
			),
			'{delivery_company}' => $delivery->company,
			'{delivery_firstname}' => $delivery->firstname,
			'{delivery_lastname}' => $delivery->lastname,
			'{delivery_address1}' => $delivery->address1,
			'{delivery_address2}' => $delivery->address2,
			'{delivery_city}' => $delivery->city,
			'{delivery_postal_code}' => $delivery->postcode,
			'{delivery_country}' => $delivery->country,
			'{delivery_state}' => isset($delivery_state->name) ? $delivery_state->name : '',
			'{delivery_phone}' => $delivery->phone ? $delivery->phone : $delivery->phone_mobile,
			'{delivery_other}' => $delivery->other,
			'{invoice_company}' => $invoice->company,
			'{invoice_firstname}' => $invoice->firstname,
			'{invoice_lastname}' => $invoice->lastname,
			'{invoice_address2}' => $invoice->address2,
			'{invoice_address1}' => $invoice->address1,
			'{invoice_city}' => $invoice->city,
			'{invoice_postal_code}' => $invoice->postcode,
			'{invoice_country}' => $invoice->country,
			'{invoice_state}' => isset($invoice_state->name) ? $invoice_state->name : '',
			'{invoice_phone}' => $invoice->phone ? $invoice->phone : $invoice->phone_mobile,
			'{invoice_other}' => $invoice->other,
			'{order_name}' => $order->reference,
			'{order_status}' => $order_state->name,
			'{shop_name}' => $configuration['PS_SHOP_NAME'],
			'{date}' => $order_date_text,
			'{carrier}' => (($carrier->name == '0') ? $configuration['PS_SHOP_NAME'] : $carrier->name),
			'{payment}' => Tools::substr($order->payment, 0, 32),
			'{items}' => $items_table,
			'{total_paid}' => $contextLocale->formatPrice($order->total_paid, $currency->iso_code),
			'{total_products}' => $contextLocale->formatPrice($total_products, $currency->iso_code),
			'{total_discounts}' => $contextLocale->formatPrice($order->total_discounts, $currency->iso_code),
			'{total_shipping}' => $contextLocale->formatPrice($order->total_shipping, $currency->iso_code),
			'{total_shipping_tax_excl}' => $contextLocale->formatPrice($order->total_shipping_tax_excl, $currency->iso_code),
			'{total_shipping_tax_incl}' => $contextLocale->formatPrice($order->total_shipping_tax_incl, $currency->iso_code),
			'{total_tax_paid}' => $contextLocale->formatPrice(
				$order->total_paid_tax_incl - $order->total_paid_tax_excl,
				$currency->iso_code
			),
			'{total_wrapping}' => $contextLocale->formatPrice($order->total_wrapping, $currency->iso_code),
			'{currency}' => $currency->sign,
			'{gift}' => (bool) $order->gift,
			'{gift_message}' => $order->gift_message,
			'{message}' => $message,
		];

		// Shop iso
		$iso = Language::getIsoById((int) Configuration::get('PS_LANG_DEFAULT'));

		// Send 1 email by merchant mail, because Mail::Send doesn't work with an array of recipients
		$merchant_new_order_emails = explode(self::__MA_MAIL_DELIMITER__, $this->merchant_new_order_emails);
		foreach ($merchant_new_order_emails as $merchant_mail) {
			// Default language
			$mail_id_lang = $id_lang;
			$mail_iso = $iso;

			// Use the merchant lang if he exists as an employee
			$results = Db::getInstance()->executeS('
				SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'employee`
				WHERE `email` = \'' . pSQL($merchant_mail) . '\'
			');
			if ($results) {
				$user_iso = Language::getIsoById((int) $results[0]['id_lang']);
				if ($user_iso) {
					$mail_id_lang = (int) $results[0]['id_lang'];
					$mail_iso = $user_iso;
				}
			}

			$dir_mail = false;
			if (file_exists(dirname(__FILE__) . '/mails/' . $mail_iso . '/new_order.txt')
				&& file_exists(dirname(__FILE__) . '/mails/' . $mail_iso . '/new_order.html')) {
				$dir_mail = dirname(__FILE__) . '/mails/';
			}

			if (file_exists(_PS_MAIL_DIR_ . $mail_iso . '/new_order.txt')
				&& file_exists(_PS_MAIL_DIR_ . $mail_iso . '/new_order.html')) {
				$dir_mail = _PS_MAIL_DIR_;
			}

			if ($dir_mail) {
				Mail::send(
					$mail_id_lang,
					'new_order',
					$this->trans(
						'New order : #%d - %s',
						[
							$order->id,
							$order->reference,
						],
						'Emails.Subject',
						$locale),
					$template_vars,
					$merchant_mail,
					null,
					$configuration['PS_SHOP_EMAIL'],
					$configuration['PS_SHOP_NAME'],
					null,
					null,
					$dir_mail,
					false,
					$id_shop
				);
			}
		}
	}
}
