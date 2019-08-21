# Magento 1.9 Integration with Geniki Taxydromiki Shipping

Allows you to create vouchers and complete orders right from within the order vire page or the order grid. Also includes a custom tracking page popup.

## Features
* Create, cancel and print individual vouchers
* Create or print mass actions
* Custom tracking popup with full details
* Automatically completes the order and includes tracking link in the order shipment emails
* Updates order status via API calls (requires curl)
* All vouchers and details are stored in the database for future reference
* Check payments for vouchers
* You can finialize all vouchers from within magento

## How to use
#### Step 1
After installing the module, go into the module configuration and set the API detaisl you got from Geniki Taxydromiki:

* API URL
* Application Key
* Username
* Password

### Step 2 (optional)
Enable sending SMS and add details (uses liveall.eu service)

## How to create a voucher
While viewing the order, click the Create Voucher button to create a voucher, add the tracking number to the shipment and notify the customer via Email and SMS (if enabled).
You can also select multiple orders from the order grid and select Create Vouchers from the mass actions dropdown

## How to cancel a voucher
On the order view page, click the Cancel/Delete Voucher. Unless the vourchers have been finalized, this will remove the voucher from the order and set it as canceled in the database. You will then be able to generate a new voucher

## How to print a voucher
Click on the Print Voucher button in the order view page and a PDF with the voucher data will be generated.

Other functions are quite self explanatory I think...
