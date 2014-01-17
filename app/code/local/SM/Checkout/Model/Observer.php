<?php
class SM_Checkout_Model_Observer {
	private $_shippingCode = 'royalmail';
	private $_country = 'GB';

	public function addShipping($params = null)
	{
		if (Mage::registry('checkout_addShipping'))
		{
			Mage::unregister('checkout_addShipping');
			return;
		}
		Mage::register('checkout_addShipping', true);
		$cart = Mage::getSingleton('checkout/cart');
		$quote = $cart->getQuote();
		if ($quote->getCouponCode() != '')
		{
			$c = Mage::getResourceModel('salesrule/rule_collection');
			$c->getSelect()->where("code=?", $quote->getCouponCode());
			foreach ($c->getItems() as $item)
			{
				$coupon = $item;
			}
			if ($coupon->getSimpleFreeShipping() > 0)
			{
				$quote->getShippingAddress()->setShippingMethod($this->_shippingCode)->save();
				return true;
			}
		}
		try
		{
			$method = $quote->getShippingAddress()->getShippingMethod();
			if ($method)
				return;
			if ($quote->getShippingAddress()->getCountryId() == '')
			{
				$quote->getShippingAddress()->setCountryId($this->_country);
			}
			$quote->getShippingAddress()->setCollectShippingRates(true);
			$quote->getShippingAddress()->collectShippingRates();
			$rates = $quote->getShippingAddress()->getAllShippingRates();
			$allowed_rates = array();
			foreach ($rates as $rate)
			{
				array_push($allowed_rates, $rate->getCode());
			}
			if (!in_array($this->_shippingCode, $allowed_rates) && count($allowed_rates) > 0)
			{
				$shippingCode = $allowed_rates[0];
			}
			if (!empty($shippingCode))
			{
				$address = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress();
				if ($address->getCountryId() == '')
					$address->setCountryId($this->_country);
				if ($address->getCity() == '')
					$address->setCity('');
				if ($address->getPostcode() == '')
					$address->setPostcode('');
				if ($address->getRegionId() == '')
					$address->setRegionId('');
				if ($address->getRegion() == '')
					$address->setRegion('');
				$address->setShippingMethod($this->_shippingCode)->setCollectShippingRates(true);
				Mage::getSingleton('checkout/session')->getQuote()->save();
			}
			else
			{
				$address = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress();
				if ($address->getCountryId() == '')
					$address->setCountryId($this->_country);
				if ($address->getCity() == '')
					$address->setCity('');
				if ($address->getPostcode() == '')
					$address->setPostcode('');
				if ($address->getRegionId() == '')
					$address->setRegionId('');
				if ($address->getRegion() == '')
					$address->setRegion('');
				$address->setShippingMethod($this->_shippingCode)->setCollectShippingRates(true);
				Mage::getSingleton('checkout/session')->getQuote()->save();
			}
			Mage::getSingleton('checkout/session')->resetCheckout();
		}
		catch (Mage_Core_Exception $e)
		{
			Mage::getSingleton('checkout/session')->addError($e->getMessage());
		}
		catch (Exception $e)
		{
			Mage::getSingleton('checkout/session')->addException($e, Mage::helper('checkout')->__('Load customer quote error'));
		}
	}

	public function getQuote()
	{
		if (empty($this->_quote))
		{
			$this->_quote = Mage::getSingleton('checkout/session')->getQuote();
		}
		return $this->_quote;
	}
}

