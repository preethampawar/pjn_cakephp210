<?php
App::uses('Validation', 'Utility');

class SalesController extends AppController
{

	public $name = 'Sales';

	public function beforeFilter()
	{
		parent::beforeFilter();
		$this->checkStoreInfo();
	}

	/**
	 * Function to show list of category products
	 */
	public function index()
	{
		$conditions = ['Sale.store_id' => $this->Session->read('Store.id')];
		$this->paginate = [
			'conditions' => $conditions,
			'order' => ['Sale.sale_date' => 'DESC', 'Sale.created' => 'DESC'],
			'limit' => 10,
			'recursive' => '-1',
		];
		$sales = $this->paginate();

		$this->set(compact('sales'));
	}

	public function add()
	{
		$error = null;

		App::uses('Product', 'Model');
		$this->Product = new Product();
		$this->Product->unbindModel(['belongsTo' => ['ProductCategory']]);
		$this->Product->bindModel(['hasOne' => ['ProductStockReport']]);
		$conditions = ['Product.store_id' => $this->Session->read('Store.id')];
		$productsInfo = $this->Product->find('all', ['conditions' => $conditions, 'order' => 'Product.name', 'recursive' => '1']);
		$productsList = $this->Product->find('list', ['conditions' => $conditions, 'order' => 'Product.name', 'recursive' => '-1']);

		if (!empty($productsInfo)) {
			foreach ($productsInfo as $row) {
				$bal_qty = $row['ProductStockReport']['balance_qty'];
				$productsList[$row['Product']['id']] = $row['Product']['name'] . ' &nbsp;&nbsp;&nbsp;[' . $bal_qty . ']';
			}
		}

		if ($this->request->isPost() or $this->request->isPut()) {
			$data = $this->request->data;
			$saleDate = $data['Sale']['sale_date']['year'] . '-' . $data['Sale']['sale_date']['month'] . '-' . $data['Sale']['sale_date']['day'];
			$data['Sale']['sale_date'] = $saleDate;
			$this->Session->delete('selectedProductID');

			$error = $this->addSaleFormValidation($data);
			// check if product is available
			if (!$error) {
				$this->Product->bindModel(['belongsTo' => ['ProductCategory']]);
				if (!$productInfo = $this->Product->findById($data['Sale']['product_id'])) {
					$error = 'Product not found.';
				}
			}

			// check if stock is available for the selected product
			if (!$error) {
				App::uses('ProductStockReport', 'Model');
				$this->ProductStockReport = new ProductStockReport();
				$conditions = ['ProductStockReport.product_id' => $data['Sale']['product_id']];
				if ($tmp = $this->ProductStockReport->find('first', ['conditions' => $conditions])) {
					$bal_qty = $tmp['ProductStockReport']['balance_qty'];
					$input_qty = $data['Sale']['total_units'];
					if ($bal_qty <= 0) {
						$error = '"' . $productInfo['Product']['name'] . '" is out of stock';
					} else if ($input_qty > $bal_qty) {
						$error = 'No. of Units cannot be greater than ' . $bal_qty;
					}
				}
			}

			if (!$error) {
				$data['Sale']['id'] = null;
				$data['Sale']['product_code'] = $productInfo['Product']['product_code'];
				$data['Sale']['product_category_id'] = $productInfo['ProductCategory']['id'];
				$data['Sale']['store_id'] = $this->Session->read('Store.id');
				$data['Sale']['sale_date'] = $saleDate;
				$data['Sale']['product_name'] = $productInfo['Product']['name'];
				$data['Sale']['category_name'] = $productInfo['ProductCategory']['name'];
				$data['Sale']['store_name'] = $this->Session->read('Store.name');

				if ($this->Sale->save($data)) {
					$this->Session->write('selectedProductID', $productInfo['Product']['id']);
					$this->Session->write('saleDate', $saleDate);
					$msg = '"' . $productInfo['Product']['name'] . '" successfully added to Sales list';
					$this->Session->setFlash($msg, 'default', ['class' => 'success']);
					$this->redirect(['controller' => 'sales', 'action' => 'add']);
				}
			}
		} else {
			if ($this->Session->check('selectedProductID')) {
				$data['Sale']['product_id'] = $this->Session->read('selectedProductID');
				$data['Sale']['sale_date'] = $this->Session->read('saleDate');
				$this->data = $data;
			}
		}

		// find recent sale products
		$conditions = ['Sale.store_id' => $this->Session->read('Store.id')];
		$saleProducts = $this->Sale->find('all', ['conditions' => $conditions, 'order' => 'Sale.created DESC', 'recursive' => '-1', 'limit' => '10']);

		if ($error) {
			$this->Session->setFlash($error);
		}
		$this->set(compact('productsInfo', 'productsList', 'saleProducts'));
	}

	public function addSaleFormValidation($data = null)
	{
		$error = null;

		if ($data) {
			if (!isset($data['Sale']['product_id'])) {
				$error = 'Product not found';
			}
			if ((!isset($data['Sale']['total_units'])) OR (!Validation::naturalNumber($data['Sale']['total_units']))) {
				$error = 'No. of Units should be greater than 0';
			}
			if ((!isset($data['Sale']['total_amount'])) OR (!Validation::decimal($data['Sale']['total_amount'])) OR ($data['Sale']['total_amount'] <= 0)) {
				$error = 'Total amount cannot be less than 0';
			}
		} else {
			$error = 'Empty product details';
		}
		return $error;
	}

	public function addInvoiceProductsFormValidation($data = null)
	{
		$error = null;
		if ($data) {
			if (!isset($data['Sale']['product_id'])) {
				$error = 'Product not found';
			}
			/*if((!isset($data['Sale']['box_qty'])) OR (!Validation::naturalNumber($data['Sale']['box_qty']))) {
				$error = 'No. of Boxes should be greater than 0';
			}*/
			if ((!isset($data['Sale']['units_in_box'])) OR (!Validation::naturalNumber($data['Sale']['units_in_box']))) {
				$error = 'Units in Box should be greater than 0';
			}
			// if ((!isset($data['Sale']['box_buying_price'])) OR (!Validation::decimal($data['Sale']['box_buying_price'])) OR ($data['Sale']['box_buying_price'] <= 0)) {
			// $error = 'Box price should be greater than 0';
			// }
			// if ((!isset($data['Sale']['special_margin'])) OR (!Validation::decimal($data['Sale']['special_margin'])) OR ($data['Sale']['special_margin'] < 0)) {
			// $error = 'Special margin should be greater than or equal to "0"';
			// }
			if ((!isset($data['Sale']['unit_price'])) OR (!Validation::decimal($data['Sale']['unit_price'])) OR ($data['Sale']['unit_price'] <= 0)) {
				$error = 'Unit price should be greater than 0';
			}
			if ((!isset($data['Sale']['total_units'])) OR (!Validation::naturalNumber($data['Sale']['total_units']))) {
				$error = 'No. of Units should be greater than 0';
			}
			if ((!isset($data['Sale']['total_amount'])) OR (!Validation::decimal($data['Sale']['total_amount'])) OR ($data['Sale']['total_amount'] <= 0)) {
				$error = 'Total amount should be greater than 0';
			}
		} else {
			$error = 'Empty product details';
		}
		return $error;
	}

	public function addProduct()
	{
		$error = null;
		// Start - Get invoice details
		if (!($invoice_info = $this->Session->read('Invoice'))) {
			$this->Session->setFlash('Invoice not found');
			$this->redirect('/invoices/');
		}

		App::uses('Invoice', 'Model');
		$this->Invoice = new Invoice();
		$this->Invoice->clear();
		$invoiceInfo = $this->Invoice->findById($invoice_info['id']);
		$this->Session->delete('Invoice');
		$this->Session->write('Invoice', $invoiceInfo['Invoice']);
		$invoiceId = $invoiceInfo['Invoice']['id'];
		$invoiceName = $invoiceInfo['Invoice']['name'];
		$franchiseId = $invoiceInfo['Invoice']['franchise_id'];
		// End - Get invoice details

		// get franchise details
		$franchiseName = '';
		$hasFranchise = $this->Session->read('Store.has_franchise');
		if ($hasFranchise) {
			App::uses('Franchise', 'Model');
			$this->Franchise = new Franchise();
			$franchiseInfo = $this->Franchise->findById($franchiseId);
			$franchiseName = isset($franchiseInfo['Franchise']['name']) ? $franchiseInfo['Franchise']['name'] : '';
		}


		App::uses('Product', 'Model');
		$this->Product = new Product();
		$this->Product->unbindModel(['belongsTo' => ['ProductCategory']]);
		$this->Product->bindModel(['hasOne' => ['ProductStockReport']]);
		$conditions = ['Product.store_id' => $this->Session->read('Store.id')];
		$productsInfo = $this->Product->find('all', ['conditions' => $conditions, 'order' => 'Product.name', 'recursive' => '1']);
		$productsList = $this->Product->find('list', ['conditions' => $conditions, 'order' => 'Product.name', 'recursive' => '-1']);

		if (!empty($productsInfo)) {
			foreach ($productsInfo as $row) {
				$bal_qty = $row['ProductStockReport']['balance_qty'];
				$productsList[$row['Product']['id']] = $row['Product']['name'] . ' &nbsp;&nbsp;&nbsp;[' . $bal_qty . ']';
			}
		}

		if ($this->request->isPost() or $this->request->isPut()) {
			$data = $this->request->data;
			$saleDate = $invoiceInfo['Invoice']['invoice_date'];
			$data['Sale']['sale_date'] = $saleDate;
			$this->Session->delete('selectedProductID');

			$error = $this->addSaleFormValidation($data);
			// check if product is available
			if (!$error) {
				$this->Product->bindModel(['belongsTo' => ['ProductCategory']]);
				if (!$productInfo = $this->Product->findById($data['Sale']['product_id'])) {
					$error = 'Product not found.';
				}
			}

			// check if stock is available for the selected product
			if (!$error) {
				App::uses('ProductStockReport', 'Model');
				$this->ProductStockReport = new ProductStockReport();
				$conditions = ['ProductStockReport.product_id' => $data['Sale']['product_id']];
				if ($tmp = $this->ProductStockReport->find('first', ['conditions' => $conditions])) {
					$bal_qty = $tmp['ProductStockReport']['balance_qty'];
					$input_qty = $data['Sale']['total_units'];
					if ($bal_qty <= 0) {
						$error = '"' . $productInfo['Product']['name'] . '" is out of stock';
					} else if ($input_qty > $bal_qty) {
						$error = 'No. of Units cannot be greater than ' . $bal_qty;
					}
				}
			}

			if (!$error) {
				$data['Sale']['id'] = null;
				$data['Sale']['product_code'] = $productInfo['Product']['product_code'];
				$data['Sale']['product_category_id'] = $productInfo['ProductCategory']['id'];
				$data['Sale']['store_id'] = $this->Session->read('Store.id');
				$data['Sale']['sale_date'] = $saleDate;
				$data['Sale']['product_name'] = $productInfo['Product']['name'];
				$data['Sale']['category_name'] = $productInfo['ProductCategory']['name'];
				$data['Sale']['store_name'] = $this->Session->read('Store.name');
				$data['Sale']['reference'] = '#SaleInvoice';
				$data['Sale']['franchise_id'] = $franchiseId;
				$data['Sale']['franchise_name'] = $franchiseName;
				$data['Sale']['invoice_id'] = $invoiceInfo['Invoice']['id'];
				$data['Sale']['invoice_name'] = $invoiceInfo['Invoice']['name'];

				if ($this->Sale->save($data)) {
					$this->updateSalesInvoice($invoiceInfo['Invoice']['id']);

					$msg = $productInfo['Product']['name'] . ' successfully added to Invoice - ' . $invoiceInfo['Invoice']['name'];
					$this->Session->setFlash($msg, 'default', ['class' => 'success']);
					$this->redirect(['controller' => 'sales', 'action' => 'addProduct']);
				}
			}
		} else {
			if ($this->Session->check('selectedProductID')) {
				$data['Sale']['product_id'] = $this->Session->read('selectedProductID');
				$data['Sale']['sale_date'] = $this->Session->read('saleDate');
				$this->data = $data;
			}
		}

		if ($error) {
			$this->Session->setFlash($error);
		}

		// find invoice products
		$conditions = ['Sale.invoice_id' => $invoiceInfo['Invoice']['id']];
		$invoiceProducts = $this->Sale->find('all', ['conditions' => $conditions, 'order' => 'Sale.created ASC', 'recursive' => '2']);

		$this->set(compact('productsInfo', 'productsList', 'invoiceInfo', 'invoiceProducts'));
	}


	public function addAllProducts()
	{
		$error = null;

		App::uses('Product', 'Model');
		$this->Product = new Product();
		$this->Product->unbindModel(['belongsTo' => ['ProductCategory']]);
		$this->Product->bindModel(['hasOne' => ['ProductStockReport']]);
		$conditions = ['Product.store_id' => $this->Session->read('Store.id')];
		$productsInfo = $this->Product->find('all', ['conditions' => $conditions, 'order' => 'Product.name', 'recursive' => '1']);
		$productsList = $this->Product->find('list', ['conditions' => $conditions, 'order' => 'Product.name', 'recursive' => '-1']);

		if (!empty($productsInfo)) {
			foreach ($productsInfo as $row) {
				$bal_qty = $row['ProductStockReport']['balance_qty'];
				$productsList[$row['Product']['id']] = $row['Product']['name'] . ' &nbsp;&nbsp;&nbsp;[' . $bal_qty . ']';
			}
		}

		if ($this->request->isPost() or $this->request->isPut()) {
			$data = $this->request->data;
			$saleDate = $data['Sale']['sale_date']['year'] . '-' . $data['Sale']['sale_date']['month'] . '-' . $data['Sale']['sale_date']['day'];
			$data['Sale']['sale_date'] = $saleDate;
			$this->Session->delete('selectedProductID');

			$error = $this->addSaleFormValidation($data);
			// check if product is available
			if (!$error) {
				$this->Product->bindModel(['belongsTo' => ['ProductCategory']]);
				if (!$productInfo = $this->Product->findById($data['Sale']['product_id'])) {
					$error = 'Product not found.';
				}
			}

			// check if stock is available for the selected product
			if (!$error) {
				App::uses('ProductStockReport', 'Model');
				$this->ProductStockReport = new ProductStockReport();
				$conditions = ['ProductStockReport.product_id' => $data['Sale']['product_id']];
				if ($tmp = $this->ProductStockReport->find('first', ['conditions' => $conditions])) {
					$bal_qty = $tmp['ProductStockReport']['balance_qty'];
					$input_qty = $data['Sale']['total_units'];
					if ($bal_qty <= 0) {
						$error = '"' . $productInfo['Product']['name'] . '" is out of stock';
					} else if ($input_qty > $bal_qty) {
						$error = 'No. of Units cannot be greater than ' . $bal_qty;
					}
				}
			}

			if (!$error) {
				$data['Sale']['id'] = null;
				$data['Sale']['product_code'] = $productInfo['Product']['product_code'];
				$data['Sale']['product_category_id'] = $productInfo['ProductCategory']['id'];
				$data['Sale']['store_id'] = $this->Session->read('Store.id');
				$data['Sale']['sale_date'] = $saleDate;
				$data['Sale']['product_name'] = $productInfo['Product']['name'];
				$data['Sale']['category_name'] = $productInfo['ProductCategory']['name'];
				$data['Sale']['store_name'] = $this->Session->read('Store.name');

				if ($this->Sale->save($data)) {
					$this->Session->write('selectedProductID', $productInfo['Product']['id']);
					$this->Session->write('saleDate', $saleDate);
					$msg = '"' . $productInfo['Product']['name'] . '" successfully added to Sales list';
					$this->Session->setFlash($msg, 'default', ['class' => 'success']);
					$this->redirect(['controller' => 'sales', 'action' => 'add']);
				}
			}
		} else {
			if ($this->Session->check('selectedProductID')) {
				$data['Sale']['product_id'] = $this->Session->read('selectedProductID');
				$data['Sale']['sale_date'] = $this->Session->read('saleDate');
				$this->data = $data;
			}
		}


		if ($error) {
			$this->Session->setFlash($error);
		}
		$this->set(compact('productsInfo', 'productsList'));
	}


	public function removeProduct($saleId = null)
	{
		if ($this->request->isPost()) {
			if ($saleInfo = $this->CommonFunctions->getSaleInfo($saleId)) {
				$this->Sale->delete($saleId);
				$this->updateSalesInvoice($saleInfo['Sale']['invoice_id']);
				$this->Session->setFlash('"' . $saleInfo['Sale']['product_name'] . '" removed from the list', 'default', ['class' => 'success']);
			} else {
				$this->Session->setFlash('Product not found');
			}
		} else {
			$this->Session->setFlash('Invalid request');
		}

		$this->redirect($this->request->referer());
	}


	/**
	 * Function to show list of category products
	 */
	public function viewClosingStock()
	{
		$conditions = ['Sale.store_id' => $this->Session->read('Store.id'), 'Sale.reference' => '#ClosingStock'];
		$this->paginate = [
			'conditions' => $conditions,
			'order' => ['Sale.created' => 'DESC'],
			'limit' => 10,
			'maxLimit' => 10,
			'recursive' => '2',
		];
		$sales = $this->paginate();

		$this->set(compact('sales'));
	}

	/**
	 * Function to add closing stock
	 */
	public function addClosingStock()
	{
		$error = null;

		App::uses('Product', 'Model');
		$this->Product = new Product();
		$conditions = ['Product.store_id' => $this->Session->read('Store.id')];

		$this->Product->unbindModel(['belongsTo' => ['ProductCategory']]);
		$this->Product->bindModel(['hasOne' => ['ProductStockReport']]);
		$productsInfo = $this->Product->find('all', ['conditions' => $conditions, 'order' => 'Product.name', 'recursive' => '1']);
		$productsList = $this->Product->find('list', ['conditions' => $conditions, 'order' => 'Product.name', 'recursive' => '-1']);

		if (!empty($productsInfo)) {
			foreach ($productsInfo as $row) {
				$bal_qty = $row['ProductStockReport']['balance_qty'];
				$productsList[$row['Product']['id']] = $row['Product']['name'] . ' &nbsp;&nbsp;&nbsp;[' . $bal_qty . ']';
				if ($this->Session->read('Store.show_brands_in_products')) {
					$brandName = ($row['Brand']['name']) ? $row['Brand']['name'] . ' - ' : '';
					$productsList[$row['Product']['id']] = $brandName . $row['Product']['name'] . ' &nbsp;&nbsp;&nbsp;[' . $bal_qty . ']';
				} else {
					$productsList[$row['Product']['id']] = $row['Product']['name'] . ' &nbsp;&nbsp;&nbsp;[' . $bal_qty . ']';
				}
			}
		}


		if ($this->request->isPost() or $this->request->isPut()) {
			$data = $this->request->data;
			$saleDate = $data['Sale']['sale_date']['year'] . '-' . $data['Sale']['sale_date']['month'] . '-' . $data['Sale']['sale_date']['day'];
			$data['Sale']['sale_date'] = $saleDate;
			$this->Session->delete('selectedProductID');

			$error = $this->addSaleFormValidation($data);
			// check if product is available
			if (!$error) {
				$this->Product->bindModel(['belongsTo' => ['ProductCategory']]);
				if (!$productInfo = $this->Product->findById($data['Sale']['product_id'])) {
					$error = 'Product not found.';
				}
			}

			// check if stock is available for the selected product
			if (!$error) {
				App::uses('ProductStockReport', 'Model');
				$this->ProductStockReport = new ProductStockReport();
				$conditions = ['ProductStockReport.product_id' => $data['Sale']['product_id']];
				if ($tmp = $this->ProductStockReport->find('first', ['conditions' => $conditions])) {
					$bal_qty = $tmp['ProductStockReport']['balance_qty'];
					$input_qty = $data['Sale']['total_units'];
					if ($bal_qty <= 0) {
						$error = '"' . $productInfo['Product']['name'] . '" is out of stock';
					} else if ($input_qty > $bal_qty) {
						$error = 'No. of Units cannot be greater than ' . $bal_qty;
					}
				}
			}

			if (!$error) {
				$data['Sale']['id'] = null;
				$data['Sale']['product_code'] = $productInfo['Product']['product_code'];
				$data['Sale']['product_category_id'] = $productInfo['ProductCategory']['id'];
				$data['Sale']['store_id'] = $this->Session->read('Store.id');
				$data['Sale']['sale_date'] = $saleDate;
				$data['Sale']['product_name'] = $productInfo['Product']['name'];
				$data['Sale']['category_name'] = $productInfo['ProductCategory']['name'];
				$data['Sale']['store_name'] = $this->Session->read('Store.name');

				if ($this->Sale->save($data)) {
					$this->Session->write('selectedProductID', $productInfo['Product']['id']);
					$this->Session->write('saleDate', $saleDate);
					$msg = $productInfo['Product']['name'] . ' successfully added to Sales list';
					$this->Session->setFlash($msg, 'default', ['class' => 'success']);
					$this->redirect(['controller' => 'sales', 'action' => 'addClosingStock']);
				}
			}
		} else {
			if ($this->Session->check('selectedProductID')) {
				$data['Sale']['product_id'] = $this->Session->read('selectedProductID');
				$data['Sale']['sale_date'] = $this->Session->read('saleDate');
				$this->data = $data;
			}
		}

		// find recent sale(closing stock) products
		$conditions = ['Sale.store_id' => $this->Session->read('Store.id'), 'Sale.reference' => '#ClosingStock'];
		$saleProducts = $this->Sale->find('all', ['conditions' => $conditions, 'order' => 'Sale.created DESC', 'recursive' => '0', 'limit' => '10']);

		if ($error) {
			$this->Session->setFlash($error);
		}
		$this->set(compact('productsInfo', 'productsList', 'saleProducts'));
	}

	/**
	 * Function to add all products closing stock
	 */
	public function addAllClosingStock()
	{
		$error = null;
		$store_id = $this->Session->read('Store.id');
		$store_name = $this->Session->read('Store.name');

		$query = "SELECT p.id, p.name, sum(pu.total_units) purchase_qty
			FROM products p
			left join purchases pu on pu.product_id = p.id
			where p.store_id=$store_id
			group by p.id;
		";
		$product_purchases = $this->Sale->query($query);
		$purchases = null;
		if ($product_purchases) {
			foreach ($product_purchases as $row) {
				$purchases[$row['p']['id']] = (int)$row[0]['purchase_qty'];
			}
		}

		$query = "SELECT p.id, p.name, sum(s.total_units) sale_qty
			FROM products p
			left join sales s on s.product_id = p.id
			where p.store_id=$store_id
			group by p.id;
		";
		$product_sales = $this->Sale->query($query);
		$sales = null;
		if ($product_sales) {
			foreach ($product_sales as $row) {
				$sales[$row['p']['id']] = (int)$row[0]['sale_qty'];
			}
		}

		$query = "SELECT p.id, p.name, sum(b.total_units) breakage_qty
			FROM products p
			left join breakages b on b.product_id = p.id
			where p.store_id=$store_id
			group by p.id;
		";
		$product_breakages = $this->Sale->query($query);
		$breakages = null;
		if ($product_breakages) {
			foreach ($product_breakages as $row) {
				$breakages[$row['p']['id']] = (int)$row[0]['breakage_qty'];
			}
		}

		App::uses('Product', 'Model');
		$this->Product = new Product();
		$conditions = ['Product.store_id' => $this->Session->read('Store.id')];
		$productsList = $this->Product->find('list', ['conditions' => $conditions, 'order' => 'Product.name', 'recursive' => '-1']);
		$productsSellingPrice = $this->Product->find('list', ['conditions' => $conditions, 'fields' => ['Product.id', 'Product.unit_selling_price'], 'order' => 'Product.name', 'recursive' => '-1']);
		$productsInfo_tmp = $this->Product->find('all', ['conditions' => $conditions, 'order' => 'Product.name', 'recursive' => '1']);
		$productsInfo = null;
		if ($productsInfo_tmp) {
			foreach ($productsInfo_tmp as $row) {
				$productsInfo[$row['Product']['id']] = $row;
			}
		}

		$products_stock = null;
		if ($productsList) {
			foreach ($productsList as $p_id => $p_name) {
				$purchase_qty = (isset($purchases[$p_id])) ? $purchases[$p_id] : 0;
				$sale_qty = (isset($sales[$p_id])) ? $sales[$p_id] : 0;
				$breakage_qty = (isset($breakages[$p_id])) ? $breakages[$p_id] : 0;
				$stock = $purchase_qty - $sale_qty - $breakage_qty;

				$products_stock[$p_id] = $stock;
			}
		}

		$success = 0;
		$salesData = null;
		if ($this->request->isPost() or $this->request->isPut()) {
			$data = $this->request->data;
			$saleDate = $data['Sale']['sale_date']['year'] . '-' . $data['Sale']['sale_date']['month'] . '-' . $data['Sale']['sale_date']['day'];
			$data['Sale']['sale_date'] = $saleDate;

			if (!empty($data['closing_stock_qty'])) {
				foreach ($data['closing_stock_qty'] as $product_id => $closing_stock_qty) {
					$available_qty = $products_stock[$product_id];
					$product_info = $productsInfo[$product_id];

					$tmp = null;
					$tmp['Sale']['id'] = null;
					$tmp['Sale']['product_code'] = $product_info['Product']['product_code'];
					$tmp['Sale']['product_id'] = $product_info['Product']['id'];
					$tmp['Sale']['product_category_id'] = $product_info['Product']['product_category_id'];
					$tmp['Sale']['store_id'] = $store_id;
					$tmp['Sale']['unit_price'] = $product_info['Product']['unit_selling_price'];

					$tmp['Sale']['sale_date'] = $saleDate;
					$tmp['Sale']['product_name'] = $product_info['Product']['name'];
					$tmp['Sale']['category_name'] = $product_info['ProductCategory']['name'];
					$tmp['Sale']['store_name'] = $store_name;
					$tmp['Sale']['closing_stock_qty'] = $closing_stock_qty;
					$tmp['Sale']['reference'] = '#ClosingStock';

					// update products whose qty is >=0
					// neglect ' ' values
					if ($closing_stock_qty != '') {
						if ($closing_stock_qty > $available_qty) {
							$error[] = $tmp['Sale']['product_name'] . ": Quantity cannot be greater than " . $available_qty;
						} else if ($closing_stock_qty < $available_qty) {
							$tmp['Sale']['total_units'] = (int)($available_qty - $closing_stock_qty);
							$tmp['Sale']['total_amount'] = ($tmp['Sale']['unit_price'] * $tmp['Sale']['total_units']);

							$salesData[] = $tmp;
						}
					}

				}

				if ($error) {
					$error = implode('<br>', $error);
				} else {
					if ($salesData) {
						foreach ($salesData as $row) {
							if ($this->Sale->save($row)) {
								$success++;
							} else {
								$error[] = $row['Sale']['product_name'] . ": Update failed";
							}
						}
						if ($error) {
							$error = implode('<br>', $error);
						}
					}
				}
			}
		}

		if ($success) {
			$success_msg = $success . ' products updated successfully';
			$this->Session->setFlash($success_msg, 'default', ['class' => 'success']);
			$this->redirect('/sales/viewClosingStock');
		}

		if ($error) {
			$this->Session->setFlash($error, 'default', ['class' => 'error']);
		}

		$this->set(compact('purchases', 'sales', 'breakages', 'products_stock', 'productsList', 'productsSellingPrice'));
	}


	public function uploadCsv()
	{
		$hideSideBar = true;

		ini_set('max_execution_time', '10000');
		ini_set('memory_limit', '256M');

		if ($this->request->isPost()) {
			$data = $this->request->data;

			if (isset($data['Sale']['csv']['error']) and (!$data['Sale']['csv']['error'])) {
				$mimes = ['application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv', 'application/octet-stream'];
				if (in_array($data['Sale']['csv']['type'], $mimes)) {
					$fileSize = $data['Sale']['csv']['size'];
					if ($fileSize > 0) {
						$maxSize = 4;
						if (ceil($fileSize / (1024 * 1024)) > $maxSize) {
							$this->Session->setFlash('File size exceeds 4Mb limit', 'default', ['class' => 'error']);
						} else {
							// valid file
							$response = $this->checkValidCsvData($data);

							if ($response['error']) {
								$this->Session->setFlash($response['msg'], 'default', ['class' => 'error']);
							} else {
								$updateResponse = $this->updateCsvData($response['fileData']);

								if ($updateResponse['error']) {
									$this->Session->setFlash($updateResponse['msg'], 'default', ['class' => 'error']);
								} else {
									$this->Session->setFlash('File uploaded successfully', 'default', ['class' => 'success']);
								}
							}
						}
					} else {
						$this->Session->setFlash('Invalid File Size', 'default', ['class' => 'error']);
					}
				} else {
					$this->Session->setFlash('Invalid CSV File', 'default', ['class' => 'error']);
				}
			} else {
				$this->Session->setFlash('Unknown File Type', 'default', ['class' => 'error']);
			}
		}

		$this->set(compact('hideSideBar', 'response', 'updateResponse'));
	}

	private function checkValidCsvData($fileInfo)
	{
		App::uses('Validation', 'Utility');
		$response = ['success' => false, 'error' => false, 'msg' => '', 'fileData' => []];

		$file = '"' . $fileInfo['Sale']['csv']['name'] . '"';
		$handle = fopen($fileInfo['Sale']['csv']['tmp_name'], 'r');
		$fileData = [];

		App::uses('Product', 'Model');
		$this->Product = new Product();
		$this->Product->displayField = 'unit_selling_price';
		$productsSellingPrice = $this->Product->find('list', ['conditions' => ['Product.store_id' => $this->Session->read('Store.id')]]);

		App::uses('ProductStockReport', 'Model');
		$this->ProductStockReport = new ProductStockReport();
		$conditions = ['ProductStockReport.store_id' => $this->Session->read('Store.id')];
		$fields = ['ProductStockReport.store_id', 'ProductStockReport.category_id', 'ProductStockReport.product_id', 'ProductStockReport.balance_qty', 'ProductStockReport.product_name', 'ProductStockReport.category_name'];
		$storeProducts = $this->ProductStockReport->find('all', ['conditions' => $conditions, 'fields' => $fields]);

		$i = 1;
		$updateSalesContent = [];
		while (($data = fgetcsv($handle)) !== false) {
			//process
			if (!empty($data)) {
				// validate number of columns
				if (count($data) != 4) {
					$response['error'] = true;
					$response['msg'] = $file . '. File should have 4 columns. File Format: (CategoryName, ProductName, ClosingStock, ClosingDate)';
				} else {
					$dataHeader = ['CategoryName', 'ProductName', 'ClosingStock', 'ClosingDate'];
					if ($dataHeader != $data) {
						// validate column data type

						// category name
						if (!Validation::notBlank($data[0])) {
							$response['error'] = true;
							$response['msg'] = 'File ' . $file . ', Line No ' . $i . ': Category name cannot be empty';
						}

						// product name
						if (!Validation::notBlank($data[1])) {
							$response['error'] = true;
							$response['msg'] = 'File ' . $file . ', Line No ' . $i . ': Product name cannot be empty';
						}

						// closing quantity
						if (!Validation::numeric($data[2])) {
							$response['error'] = true;
							$response['msg'] = 'File ' . $file . ', Line No ' . $i . ': Closing stock should be greater than or equal to 0. eg: 0,1,2,3,4,5,... etc';
						} else {
							if ($data[2] > 0) {
								if (!Validation::naturalNumber($data[2])) {
									$response['error'] = true;
									$response['msg'] = 'File ' . $file . ', Line No ' . $i . ': Invalid Closing stock value. Should be >= 0. eg: 0,1,2,3,4,5,... etc';
								}
							}
						}

						// closing date
						if (Validation::notBlank($data[3])) {
							if (date('d-m-Y', strtotime($data[3])) != $data[3]) {
								$response['error'] = true;
								$response['msg'] = 'File ' . $file . ', Line No ' . $i . ': Invalid Date';
							}
						} else {
							$response['error'] = true;
							$response['msg'] = 'File ' . $file . ', Line No ' . $i . ': Date column cannot be empty';
						}

						// check if category & product exists for the selected store.
						$tmpCategoryName = htmlentities($data[0], ENT_QUOTES);
						$tmpProductName = htmlentities($data[1], ENT_QUOTES);
						$tmpQty = $data[2];
						if (!empty($storeProducts)) {
							$category_product_found = false;
							foreach ($storeProducts as $row) {
								if (($row['ProductStockReport']['category_name'] == $tmpCategoryName) and ($row['ProductStockReport']['product_name'] == $tmpProductName)) {
									$category_product_found = true;
									$productID = $row['ProductStockReport']['product_id'];
									$categoryID = $row['ProductStockReport']['category_id'];

									// validate product balance stock and closing stock
									if ($row['ProductStockReport']['balance_qty'] < $tmpQty) {
										$response['error'] = true;
										$response['msg'] = 'File ' . $file . ', Line No ' . $i . ': Closing quantity cannot be greater than "' . $row['ProductStockReport']['balance_qty'] . '"';
									} else {
										if ($row['ProductStockReport']['balance_qty'] != $tmpQty) {
											// check unit selling price
											if ($productsSellingPrice[$productID]) {
												$totalUnits = $row['ProductStockReport']['balance_qty'] - $tmpQty;
												$totalAmount = $totalUnits * $productsSellingPrice[$productID];
												$saleDate = date('Y-m-d', strtotime($data[3]));

												$updateSalesContent[$i]['id'] = null;
												$updateSalesContent[$i]['product_id'] = $productID;
												$updateSalesContent[$i]['product_category_id'] = $categoryID;
												$updateSalesContent[$i]['store_id'] = $this->Session->read('Store.id');
												$updateSalesContent[$i]['unit_price'] = $productsSellingPrice[$productID];
												$updateSalesContent[$i]['total_units'] = $totalUnits;
												$updateSalesContent[$i]['total_amount'] = $totalAmount;
												$updateSalesContent[$i]['sale_date'] = $saleDate;
												$updateSalesContent[$i]['product_name'] = $tmpProductName;
												$updateSalesContent[$i]['category_name'] = $tmpCategoryName;
												$updateSalesContent[$i]['store_name'] = $this->Session->read('Store.name');
												$updateSalesContent[$i]['closing_stock_qty'] = $tmpQty;
												$updateSalesContent[$i]['reference'] = '#ClosingStock';
											} else {
												$response['error'] = true;
												$response['msg'] = 'File ' . $file . ', Line No ' . $i . ': Unit Selling Price not defined for the product "' . $tmpProductName . '"';
											}

										}
									}

									break;
								}
							}

							if (!$category_product_found) {
								$response['error'] = true;
								$response['msg'] = 'File ' . $file . ', Line No ' . $i . ': Category/Product not found';
							}
						} else {
							$response['error'] = true;
							$response['msg'] = 'No products found';
						}

						//set data
						if (!$response['error']) {
							$fileData = $updateSalesContent;
						}
					}
				}
			}

			if ($response['error'] == true) {
				break;
			}

			$i++;
		}
		$response['fileData'] = $fileData;

		return $response;
	}

	private function updateCsvData($fileData)
	{
		$response = ['success' => false, 'error' => false, 'msg' => '', 'info' => []];
		$errorMsg = [];
		$totalRecords = 0;
		$savedRecords = 0;
		$failedRecords = 0;
		if (!empty($fileData)) {
			$totalRecords = count($fileData);
			foreach ($fileData as $row) {
				$tmp = [];
				$tmp['Sale'] = $row;
				if ($this->Sale->save($tmp)) {
					$savedRecords++;
				} else {
					$errorMsg[] = 'Failed to add closing stock for: Category: "' . $row['Sale']['category_name'] . '", Product: "' . $row['Sale']['product_name'] . '"';
					$failedRecords++;
				}
			}
		}

		if ($errorMsg) {
			$response['error'] = true;
			$response['msg'] = implode($errorMsg, '<br>');
		}
		$response['info']['totalRecords'] = $totalRecords;
		$response['info']['savedRecords'] = $savedRecords;
		$response['info']['failedRecords'] = $failedRecords;

		return $response;
	}

}

?>
