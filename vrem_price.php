<?
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../..");
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true);
set_time_limit(0);
//define("LANG", "ru");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $DB;
global $APPLICATION;

CModule::IncludeModule("iblock");
CModule::IncludeModule("catalog");
CModule::IncludeModule("sale");

$iTime = 0;
$Price_min_au = 0;
$currRub = 'RUB';
$arSelect = Array("ID");
$arFilter = Array("IBLOCK_ID"=>5, "ACTIVE"=>"Y"); //5 товары 6 предложения
$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
while($ob = $res->GetNextElement())
{
   $arFields = $ob->GetFields();
   
   // Пауза для снижения нагрузки
   if($iTime == 25)
   {
      $iTime = 0;
      sleep(1);
   }

    $offer_true = 0;
	$intIBlockID = 5; 
	$mxResult = CCatalogSKU::GetInfoByProductIBlock($intIBlockID); 
	if (is_array($mxResult)) 
	{
		$rsOffers = CIBlockElement::GetList(array("PRICE"=>"ASC"),array('IBLOCK_ID' => $mxResult['IBLOCK_ID'], 'PROPERTY_'.$mxResult['SKU_PROPERTY_ID'] => $arFields['ID'], "ACTIVE"=>"Y")); 
		$i = 0;
		$price_offer_sort = 0;
		while ($arOffer = $rsOffers->GetNext())
		{
		   // получаем базовую цену товара
		$ar_res_offer = GetCatalogProductPrice($arOffer["ID"], 1); 
	   
	    //получаем цену со скидкой если она существует
	    $db_resSale_offer = CPrice::GetListEx(
		  array(),
		  array(
			 "PRODUCT_ID" => $arOffer["ID"],
			 "CATALOG_GROUP_ID" => 2
		  )
	    );		
		
		//если цена со скидкой сущетвует то заменяем базовую цену новым значением для обработки в массиве
		if ($ar_resSale_offer = $db_resSale_offer->Fetch())
		{
		//обновляем цену
		$ar_res_offer["PRICE"] = $ar_resSale_offer["PRICE"];
		}
		// Валюта
		$currRub = 'RUB';
		// Конвертация цены в выбранную валюту
		$newCurrRub = Round(CCurrencyRates::ConvertCurrency($ar_res_offer['PRICE'], $ar_res_offer['CURRENCY'], $currRub), 1);
		//break;
		$offer_true = 1;
		$i++;
		if($i == 1){
			$price_offer_sort = $newCurrRub;
		}
		else{
			if(!$price_offer_sort == 0){
				if($price_offer_sort > $newCurrRub){
					$price_offer_sort = $newCurrRub;
				}
			}
			else{
				$price_offer_sort = $newCurrRub;
			}			
		}
		
		}
	}
	
	$Price_min_au = $price_offer_sort;

	if($offer_true == 0){
	
	   // получаем базовую цену товара
	   $ar_res = CPrice::GetBasePrice($arFields['ID']);
	   
	   // получаем цену со скидкой если она существует
	    $db_resSale = CPrice::GetListEx(
		   array(),
		   array(
			  "PRODUCT_ID" => $arFields['ID'],
			  "CATALOG_GROUP_ID" => 2
		   )
	    );
	   //если цена со скидкой сущетвует то заменяем базовую цену новым значением для обработки в массиве
	   if ($ar_resSale = $db_resSale->Fetch())
	   {
	   //   обновляем цену
	   $ar_res["PRICE"] = $ar_resSale["PRICE"];
	   }
	   
	   /********************************************/
	   
	   // Валюта
	   $currRub = 'RUB';
	   // Конвертация цены в выбранную валюту
	   $newCurrRubTov = Round(CCurrencyRates::ConvertCurrency($ar_res['PRICE'], $ar_res['CURRENCY'], $currRub), 1);
	   
	   $Price_min_au = $newCurrRubTov;

	}
	
   //рабочий пример, изменяет значение свойства "PRICE_SORT" в элементе инфоблока с ID 2438 из инфоблока с ID 20
   //CIBlockElement::SetPropertyValuesEx($arFields['ID'], 20, array("PRICE_SORT" => $Price_min_au));
   
   // Массив с новыми занчениями 
   $arFieldsNewPriceRub = Array(
      "PRODUCT_ID" => $arFields['ID'],
      "CATALOG_GROUP_ID" => 3,
      "PRICE" => $Price_min_au,
      "CURRENCY" => $currRub
   );
   // Массив обновляемого товара (ID товара и тип обновляемой цены)
   $resNewPriceRub = CPrice::GetList(
      array(),
      array(
         "PRODUCT_ID" => $arFields['ID'],
         "CATALOG_GROUP_ID" => 3
      )
   );
   // обновление цены если цена существует, в противном случае она добавится
   if ($arrNewPriceRub = $resNewPriceRub->Fetch())
   {
      CPrice::Update($arrNewPriceRub["ID"], $arFieldsNewPriceRub);
   }else{
      CPrice::Add($arFieldsNewPriceRub);
   }
	
   /********************************************/
   
   $iTime++;
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>