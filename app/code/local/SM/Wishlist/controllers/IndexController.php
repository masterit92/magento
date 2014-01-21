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
		$this->_redirectreferer('*/*/');
	}
}