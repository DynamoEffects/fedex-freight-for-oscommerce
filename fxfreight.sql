ALTER TABLE `products` 
ADD `products_fxf_class` VARCHAR( 3 ) DEFAULT '050' NOT NULL ,
ADD `products_fxf_desc` VARCHAR( 100 ) ,
ADD `products_fxf_nmfc` VARCHAR( 100 ) ,
ADD `products_fxf_haz` TINYINT DEFAULT '0' NOT NULL ,
ADD `products_fxf_freezable` TINYINT DEFAULT '0' NOT NULL ;