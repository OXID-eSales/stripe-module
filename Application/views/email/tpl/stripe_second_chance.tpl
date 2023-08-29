[{assign var="shop"      value=$oEmailView->getShop()}]
[{assign var="oViewConf" value=$oEmailView->getViewConfig()}]

[{include file="email/html/header.tpl" title=$subject}]

[{oxcontent ident="stripesecondchanceemail"}]<br/><br/>

[{include file="email/html/footer.tpl"}]
