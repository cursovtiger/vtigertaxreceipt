<?php
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
*
 ********************************************************************************/
/**
 * Function to generate DeliveryOrder pdf
 */
/* SCOTT BROKEN function addTitleTextBlock($pd, $title,$positions )   
{
        $r1  = $positions[0];
        $y1  = $positions[1];
        $pd->SetXY( $r1, $y1);
        $pd->SetFont( "Helvetica", "B", 24);
        $pd->Cell( $positions[2], 4,$title);
        $pd->SetFont( "Helvetica", "", 10); 
$pd
}  */
function getContactPhone($contact_id)
{
      require_once('include/utils/utils.php'); //new
	  require_once('include/utils/RecurringType.php');
	
        global $log;
        $log->debug("Entering getContactPhone(".$contact_id.") method ...");
        $log->info("in getContactPhone ".$contact_id);

        global $adb, $current_user;
        $contact_phone = '';
        if($contact_id != '')
        {
                $sql = "select * from vtiger_contactdetails where contactid=?";
                $result = $adb->pquery($sql, array($contact_id));
                $contact_phone .= "Phone: ";
                $contact_phone .= $adb->query_result($result,0,"phone");
         // scott  not wanted     $contact_phone .= "\nMobile: ";
        // scott        $contact_phone .= $adb->query_result($result,0,"mobile");
               
        }
        $log->debug("Exiting getContactName method ...");
        return $contact_phone;
}


function get_do_pdf() {
	
	require_once('include/tcpdf/pdf.php');
	require_once('include/tcpdf/pdfconfig.php');
	require_once('include/database/PearDatabase.php');
	require_once('modules/Taxreceipt/Taxreceipt.php');
	require_once('modules/Invoice/Invoice.php');
	require_once('modules/SalesOrder/SalesOrder.php');
	require_once('modules/Taxreceipt/language/en_us.lang.php');
	global $adb,$app_strings,$current_user, $mod_strings;

	
	// would you like and end page?  1 for yes 0 for no
	$endpage="1";
	
	$id = $_REQUEST['src_record'];
	// scott	Print_r($_REQUEST);
	//retreiving the vtiger_invoice info
	$focus = new Taxreceipt();
	
	$focus->retrieve_entity_info($_REQUEST['src_record'],"Taxreceipt");
//	$focus->retrieve_entity_info($_REQUEST['LinkTo'],"SalesOrder");
	$focus->apply_field_security();
   // scott Print_r($focus->column_fields);
   
	$productlist = $focus->column_fields["ProductList"];
	$customerpo = $focus->column_fields["CustomerPO"];
	$tr_number = $focus->column_fields["dn_sequence"];

	$inv_no = $focus->column_fields['LinkTo'];
// scott	Print($productlist);
	$focus = new Invoice();
	$focus->retrieve_entity_info($inv_no,"Invoice");
//scott	Print_r($focus);
	
	$sql="select currency_symbol from vtiger_currency_info where id=?";
	$result = $adb->pquery($sql, array($focus->column_fields['currency_id']));
	$currency_symbol = $adb->query_result($result,0,'currency_symbol');
	
	// **************** BEGIN POPULATE DATA ********************
	// populate data
	if($focus->column_fields["quote_id"] != '')
		$quote_name = getQuoteName($focus->column_fields["quote_id"]);
	else
		$quote_name = '';
 	if($focus->column_fields["account_id"] != '')
		$account_name = getAccountName($focus->column_fields["account_id"]);
	else
		$account_name = '';
    $contact_phone='';
	if($focus->column_fields["contact_id"] != '') {
		$contact_name = getContactName($focus->column_fields["contact_id"]);
    	$contact_phone = getContactPhone($focus->column_fields["contact_id"]);  }
	else
		$contact_name = '';		
	$po_name = $focus->column_fields["purchaseorder"];
	$subject = $focus->column_fields["subject"];
	//scott
	$job_number = $focus->column_fields["subject"];
	
	$valid_till = $focus->column_fields["duedate"];
	$valid_till = getValidDisplayDate($valid_till);  
	$bill_street = $focus->column_fields["bill_street"];
	$bill_city = $focus->column_fields["bill_city"];
	$bill_state = $focus->column_fields["bill_state"];
	$bill_code = $focus->column_fields["bill_code"];
	$bill_country = $focus->column_fields["bill_country"];
	$contact_name =getContactName($focus->column_fields["contact_id"]);
	
	$ship_street = $focus->column_fields["ship_street"];
	$ship_city = $focus->column_fields["ship_city"];
	$ship_state = $focus->column_fields["ship_state"];
	$ship_code = $focus->column_fields["ship_code"];
	$ship_country = $focus->column_fields["ship_country"];
	
	$conditions = from_html($focus->column_fields["terms_conditions"]);
	$description = from_html($focus->column_fields["description"]);
	$status = $focus->column_fields["sostatus"];
	
	// Company information
	$add_query = "select * from vtiger_organizationdetails";
	$result = $adb->pquery($add_query, array());
	$num_rows = $adb->num_rows($result);
	
	if($num_rows > 0)
	{
		$org_name = $adb->query_result($result,0,"organizationname");
		$org_address = $adb->query_result($result,0,"address");
		$org_city = $adb->query_result($result,0,"city");
		$org_state = $adb->query_result($result,0,"state");
		$org_country = $adb->query_result($result,0,"country");
		$org_code = $adb->query_result($result,0,"code");
		$org_phone = $adb->query_result($result,0,"phone");
		$org_fax = $adb->query_result($result,0,"fax");
		$org_website = $adb->query_result($result,0,"website");
	
		$logo_name = $adb->query_result($result,0,"logoname");
	}
	
	//Population of Product Details - Starts
	
	//we can cut and paste the following lines in a file and include that file here is enough. For that we have to put a new common file. we will do this later
	//NOTE : Removed currency symbols and added with Grand Total text. it is enough to show the currency symbol in one place
	
	//we can also get the NetTotal, Final Discount Amount/Percent, Adjustment and GrandTotal from the array $associated_products[1]['final_details']
	
	//get the Associated Products for this Invoice
	$focus->id = $focus->column_fields["record_id"];
	$associated_products = getAssociatedProducts("SalesOrder",$focus);
	$num_products = count($associated_products);
	
	//This $final_details array will contain the final total, discount, Group Tax, S&H charge, S&H taxes and adjustment
	$final_details = $associated_products[1]['final_details'];
	
	//getting the Net Total
	$price_subtotal = number_format($final_details["hdnSubTotal"],2,'.',',');
	
	//Final discount amount/percentage
	$discount_amount =$final_details["discount_amount_final"];
	$discount_percent =$final_details["discount_percentage_final"];
	
	if($discount_amount != "")
		$price_discount = number_format($discount_amount,2,'.',',');
	else if($discount_percent != "")
	{
		//This will be displayed near Discount label - used in include/fpdf/templates/body.php
		$final_price_discount_percent = "(".number_format($discount_percent,2,'.',',')." %)";
		$price_discount = number_format((($discount_percent*$final_details["hdnSubTotal"])/100),2,'.',',');
	}
	else
		$price_discount = "0.00";
	
	//Adjustment
	$price_adjustment = number_format($final_details["adjustment"],2,'.',',');
	//Grand Total
	$price_total = number_format($final_details["grandTotal"],2,'.',',');
	
	
	
	//To calculate the group tax amount
	if($final_details['taxtype'] == 'group')
	{
		$group_tax_total = $final_details['tax_totalamount'];
		$price_salestax = number_format($group_tax_total,2,'.',',');
	
		$group_total_tax_percent = '0.00';
		$group_tax_details = $final_details['taxes'];
		for($i=0;$i<count($group_tax_details);$i++)
		{
			$group_total_tax_percent = $group_total_tax_percent+$group_tax_details[$i]['percentage'];
		}
	}
	
	//S&H amount
	$sh_amount = $final_details['shipping_handling_charge'];
	$price_shipping = number_format($sh_amount,2,'.',',');
	
	//S&H taxes
	$sh_tax_details = $final_details['sh_taxes'];
	$sh_tax_percent = '0.00';
	for($i=0;$i<count($sh_tax_details);$i++)
	{
		$sh_tax_percent = $sh_tax_percent + $sh_tax_details[$i]['percentage'];
	}
	$sh_tax_amount = $final_details['shtax_totalamount'];
	$price_shipping_tax = number_format($sh_tax_amount,2,'.',',');
	
	$prod_line = array();
	$lines = 0;
	$prod_item= explode(",",$productlist) ;  //updated by scott
    $num_products = count($prod_item);   // updated by scott
   if($productlist=='')   
   {
         	$num_products = count($associated_products); //if product list empty
            for($k=1;$k<=$num_products;$k++)
            {
	          $prod_item[$k-1]=$k;
	        }
	  //	Print_r($prod_item);
    }
//	Print_r($prod_item);
//	Print($num_products);
	//This is to get all prodcut details as row basis
//	Print_r($associated_products);
	for($k=1,$j=$k-1;$k<=$num_products;$k++,$j++)
	{
		$i=$prod_item[$k-1];
		$product_name[$i] = $associated_products[$i]['productName'.$i];
		$subproduct_name[$i] = split("<br>",$associated_products[$i]['subprod_names'.$i]);
		//$prod_description[$i] = $associated_products[$i]['productDescription'.$i];
		$comment[$i] = $associated_products[$i]['comment'.$i];
		$product_id[$i] = $associated_products[$i]['hdnProductId'.$i];
		$qty[$i] = $associated_products[$i]['qty'.$i];
		$unit_price[$i] = number_format($associated_products[$i]['unitPrice'.$i],2,'.',',');
		$list_price[$i] = number_format($associated_products[$i]['listPrice'.$i],2,'.',',');
		$list_pricet[$i] = $associated_products[$i]['listPrice'.$i];
		$discount_total[$i] = $associated_products[$i]['discountTotal'.$i];
	        //aded for 5.0.3 pdf changes
	        $product_code[$i] = $associated_products[$i]['hdnProductcode'.$i];
		
		$taxable_total = $qty[$i]*$list_pricet[$i]-$discount_total[$i];
	
		$producttotal = $taxable_total;
		$total_taxes = '0.00';
		if($focus->column_fields["hdnTaxType"] == "individual")
		{
			$total_tax_percent = '0.00';
			//This loop is to get all tax percentage and then calculate the total of all taxes
			for($tax_count=0;$tax_count<count($associated_products[$i]['taxes']);$tax_count++)
			{
				$tax_percent = $associated_products[$i]['taxes'][$tax_count]['percentage'];
				$total_tax_percent = $total_tax_percent+$tax_percent;
				$tax_amount = (($taxable_total*$tax_percent)/100);
				$total_taxes = $total_taxes+$tax_amount;
			}
			$producttotal = $taxable_total+$total_taxes;
			$product_line[$j]["Tax"] = number_format($total_taxes,2,'.',',')."\n ($total_tax_percent %) ";
			$price_salestax += $total_taxes;
		}
		$prod_total[$i] = number_format($producttotal,2,'.',',');
		$product_line[$j]["Count"] = $i.".";
		$product_line[$j]["Product Code"] = $product_code[$i];
		$product_line[$j]["Qty"] = $qty[$i];
	    $product_line[$j]["Units"] = "test"; //scott $qty[$i];
		$product_line[$j]["Price"] = $list_price[$i];
		$product_line[$j]["Discount"] = $discount_total[$i];
		$product_line[$j]["Total"] = $prod_total[$i];

		$lines++;
		$product_line[$j]["Product Name"] = decode_html($product_name[$i]);

		$prod_line[$j]=1;
		for($count=0;$count<count($subproduct_name[$i]);$count++){
			if($lines % 24!=0){
				$product_line[$j]["Product Name"] .= "\n".decode_html($subproduct_name[$i][$count]);
				$prod_line[$j]++;
			}
			else{
				$j++;
				$product_line[$j]["Product Name"] = decode_html($product_name[$i]);
				$product_line[$j]["Product Name"] .= "\n".decode_html($subproduct_name[$i][$count]);
				$prod_line[$j]=2;
				$lines++;
			}
			$lines++;
		}
		if ($comment[$i] != ''){
			$product_line[$j]["Product Name"] .= "\n".decode_html($comment[$i]);
			$prod_line[$j]++;
			$lines++;
		}
	}
	$price_salestax = number_format($price_salestax,2,'.',','); 
	//echo '<pre>Product Details ==>';print_r($product_line);echo '</pre>';
	//echo '<pre>';print_r($associated_products);echo '</pre>';
	
	
	//Population of Product Details - Ends
	
	
	// ************************ END POPULATE DATA ***************************8
	
	$page_num='1';
	$pdf = new PDF( 'P', 'mm', 'A4' );
	$pdf->Open();
	
//	$num_pages=ceil(($num_products/$products_per_page));
	//STARTS - Placement of products in pages as per the lines count
	$lines_per_page = "24";
	$prod_cnt=0;
	$num_pages = ceil(($lines/$lines_per_page));
	$tmp=0;
	for($count=0;$count<$num_pages;$count++){
		for($k=$tmp;$k<$j;$k++){
			if($prod_cnt!=24){
				$products[$count][]= $k;
				$prod_cnt += $prod_line[$k];
			} else {
				$tmp=$k;
				$prod_cnt = 0;
				break;
			}
		}
	}
	//ENDS - Placement of products in pages as per the lines count
	
	
	$current_product=0;
	for($l=0;$l<$num_pages;$l++)
	{
		$line=array();
		if($num_pages == $page_num)
			$lastpage=1;
	
		/*while($current_product != $page_num*$products_per_page)
		{
			$line[]=$product_line[$current_product];
			$current_product++;
		}/*/
		foreach($products[$l] as $index=>$key){
			
			$line[] = $product_line[$key];
		}
	
		$pdf->AddPage();
		include("modules/Taxreceipt/pdf_templates/header.php");
		include("modules/Taxreceipt/pdf_templates/dobody.php");
	
		//if bottom > 145 then we skip the Description and T&C in every page and display only in lastpage
		//if you want to display the description and T&C in each page then set the display_desc_tc='true' and bottom <= 145 in pdfconfig.php
		if($display_desc_tc == 'true')
		if($bottom <= 145)
		{
			include("modules/Taxreceipt/pdf_templates/footer.php");
		}
	
		$page_num++;
	
	//scott last page remove:	if (($endpage) && ($lastpage))
		//scott last page remove:	{
		//scott last page remove:		$pdf->AddPage();
		//scott last page remove:		include("modules/Taxreceipt/pdf_templates/header.php");
		//scott last page remove:		include("modules/Taxreceipt/pdf_templates/lastpage/body.php");
		//scott last page remove:		include("modules/Taxreceipt/pdf_templates/lastpage/footer.php");
		//scott last page remove:	}
	}	
	return $pdf;
}
?>