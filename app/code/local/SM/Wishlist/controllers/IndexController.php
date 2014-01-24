<?php
#
# Controllers are not autoloaded so we will have to do it manually:
#
require_once 'Mage/Wishlist/controllers/IndexController.php';

class SM_Wishlist_IndexController extends Mage_Wishlist_IndexController {
	# Overloaded
	public function updateAction()
	{
		if (!$this->_validateFormKey())
		{
			$this->_redirectreferer('*/*/');
		}
		$wishlist = $this->_getWishlist();
		if (!$wishlist)
		{
			return $this->norouteAction();
		}

		$post = $this->getRequest()->getPost();
		if ($post && isset($post['description']) && is_array($post['description']))
		{
			$updatedItems = 0;

			foreach ($post['description'] as $itemId => $description)
			{
				$item = Mage::getModel('wishlist/item')->load($itemId);
				if ($item->getWishlistId() != $wishlist->getId())
				{
					continue;
				}

				// Extract new values
				$description = (string)$description;

				if ($description == Mage::helper('wishlist')->defaultCommentString())
				{
					$description = '';
				}
				elseif (!strlen($description))
				{
					$description = $item->getDescription();
				}

				$qty = null;
				if (isset($post['qty'][$itemId]))
				{
					$qty = $this->_processLocalizedQty($post['qty'][$itemId]);
				}
				if (is_null($qty))
				{
					$qty = $item->getQty();
					if (!$qty)
					{
						$qty = 1;
					}
				}
				elseif (0 == $qty)
				{
					try
					{
						$item->delete();
					}
					catch (Exception $e)
					{
						Mage::logException($e);
						Mage::getSingleton('customer/session')->addError($this->__('Can\'t delete item from wishlist'));
					}
				}

				// Check that we need to save
				if (($item->getDescription() == $description) && ($item->getQty() == $qty))
				{
					continue;
				}
				try
				{
					$item->setDescription($description)->setQty($qty)->save();
					$updatedItems++;
				}
				catch (Exception $e)
				{
					Mage::getSingleton('customer/session')->addError($this->__('Can\'t save description %s', Mage::helper('core')->escapeHtml($description)));
				}
			}

			// save wishlist model for setting date of last update
			if ($updatedItems)
			{
				try
				{
					$wishlist->save();
					Mage::helper('wishlist')->calculate();
				}
				catch (Exception $e)
				{
					Mage::getSingleton('customer/session')->addError($this->__('Can\'t update wishlist'));
				}
			}

			if (isset($post['save_and_share']))
			{
				$this->_redirect('*/*/share', array('wishlist_id' => $wishlist->getId()));
				return;
			}
		}
		return $this->_redirectReferer('*/*');
	}

	public function fromAllcartAction()
	{
		$wishlist = $this->_getWishlist();
		if (!$wishlist)
		{
			return $this->norouteAction();
		}

		$arr_itemId = explode(',', $this->getRequest()->getParam('item'));


		/* @var Mage_Checkout_Model_Cart $cart */
		$cart = Mage::getSingleton('checkout/cart');
		$session = Mage::getSingleton('checkout/session');
		foreach ($arr_itemId as $itemId)
		{
			try
			{
				$item = $cart->getQuote()->getItemById((int)$itemId);

				if (!$item)
				{
					Mage::throwException(Mage::helper('wishlist')->__("Requested cart item doesn't exist"));
				}

				$productId = $item->getProductId();
				$buyRequest = $item->getBuyRequest();

				$wishlist->addNewItem($productId, $buyRequest);

				$productIds[] = $productId;
				$cart->getQuote()->removeItem($itemId);
				$cart->save();
				Mage::helper('wishlist')->calculate();
				$productName = Mage::helper('core')->escapeHtml($item->getProduct()->getName());
				$wishlistName = Mage::helper('core')->escapeHtml($wishlist->getName());
				$session->addSuccess(Mage::helper('wishlist')->__("%s has been moved to wishlist %s", $productName, $wishlistName));
				$wishlist->save();
			}
			catch (Mage_Core_Exception $e)
			{
				$session->addError($e->getMessage());
			}
			catch (Exception $e)
			{
				$session->addException($e, Mage::helper('wishlist')->__('Cannot move item to wishlist'));
			}
		}
		return $this->_redirectUrl(Mage::helper('checkout/cart')->getCartUrl());
	}

	/**
	 * Remove item
	 */
	public function multipleRemoveAction()
	{
		$arr_itemId = explode(',', (int)$this->getRequest()->getParam('item'));
		//		$form_key = $this->getRequest()->getParam('form_key');
		//		$product = $this->getRequest()->getParam('product');
		//		$related_product = $this->getRequest()->getParam('related_product');
		//		$url_add_to_cart = "checkout/cart/add?form_key=$form_key&product=$product&related_product=$related_product";
		foreach ($arr_itemId as $item_id)
		{
			$this->addWishlistToCart($item_id);
			$item = Mage::getModel('wishlist/item')->load($item_id);
			if (!$item->getId())
			{
				return $this->norouteAction();
			}
			$wishlist = $this->_getWishlist($item->getWishlistId());
			if (!$wishlist)
			{
				return $this->norouteAction();
			}
			try
			{
				$item->delete();
				$wishlist->save();
			}
			catch (Mage_Core_Exception $e)
			{
				Mage::getSingleton('customer/session')->addError($this->__('An error occurred while deleting the item from wishlist: %s', $e->getMessage()));
			}
			catch (Exception $e)
			{
				Mage::getSingleton('customer/session')->addError($this->__('An error occurred while deleting the item from wishlist.'));
			}
		}
		Mage::helper('wishlist')->calculate();

		$this->_redirectreferer('*/*/');
		//		$this->_redirect('checkout/cart/');
		//$this->_redirect($url_add_to_cart);
	}

	protected function addWishlistToCartAction()
	{
		$arr_itemId = explode(',', $this->getRequest()->getParam('item'));
		$arr_qty = explode(',', $this->getRequest()->getParam('qty'));
		$i=0;
		foreach ($arr_itemId as $id)
		{
			$this->addSelectedWishlist($id,$arr_qty[$i]);
			$i++;
		}
		return $this->_redirectReferer('*/*');
	}

	protected function addSelectedWishlist($itemId,$qty=NULL)
	{
		/* @var $item Mage_Wishlist_Model_Item */
		$item = Mage::getModel('wishlist/item')->load($itemId);
		if (!$item->getId())
		{
			return $this->_redirectReferer('*/*');
		}
		$wishlist = $this->_getWishlist($item->getWishlistId());
		if (!$wishlist)
		{
			return $this->_redirectReferer('*/*');
		}
		//	 Set qty
		$qty = $this->_processLocalizedQty($qty);
		if ($qty) {
			$item->setQty($qty);
		}
		/* @var $session Mage_Wishlist_Model_Session */
		$session = Mage::getSingleton('wishlist/session');
		$cart = Mage::getSingleton('checkout/cart');
		try
		{
			$options = Mage::getModel('wishlist/item_option')->getCollection()->addItemFilter(array($itemId));
			$item->setOptions($options->getOptionsByItem($itemId));

			$buyRequest = Mage::helper('catalog/product')->addParamsToBuyRequest($this->getRequest()->getParams(), array('current_config' => $item->getBuyRequest()));

			$item->mergeBuyRequest($buyRequest);
			$wishlist->save();
			if ($item->addToCart($cart, true))
			{
				$cart->save()->getQuote()->collectTotals();
			}
			Mage::helper('wishlist')->calculate();
		}
		catch (Mage_Core_Exception $e)
		{
			if ($e->getCode() == Mage_Wishlist_Model_Item::EXCEPTION_CODE_NOT_SALABLE)
			{
				$session->addError($this->__('This product(s) is currently out of stock'));
			}
			else if ($e->getCode() == Mage_Wishlist_Model_Item::EXCEPTION_CODE_HAS_REQUIRED_OPTIONS)
			{
				Mage::getSingleton('catalog/session')->addNotice($e->getMessage());
			}
			else
			{
				Mage::getSingleton('catalog/session')->addNotice($e->getMessage());
			}
		}
		catch (Exception $e)
		{
			Mage::logException($e);
			$session->addException($e, $this->__('Cannot add item to shopping cart'));
		}
		Mage::helper('wishlist')->calculate();
	}
}