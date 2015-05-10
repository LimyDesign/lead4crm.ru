<?php
session_start();
require_once __DIR__.'/src/vendor/autoload.php';
$conf = json_decode(file_get_contents(__DIR__.'/config.json'), true);

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, true);

use Everzet\Jade\Dumper\PHPDumper,
	Everzet\Jade\Visitor\AutotagsVisitor,
	Everzet\Jade\Filter\JavaScriptFilter,
	Everzet\Jade\Filter\CDATAFilter,
	Everzet\Jade\Filter\PHPFilter,
	Everzet\Jade\Filter\CSSFilter,
	Everzet\Jade\Parser,
	Everzet\Jade\Lexer\Lexer,
	Everzet\Jade\Jade;

$dumper = new PHPDumper();
$dumper->registerVisitor('tag', new AutotagsVisitor());
$dumper->registerFilter('php', new PHPFilter());
$dumper->registerFilter('cdata', new CDATAFilter());
$dumper->registerFilter('style', new CSSFilter());
$dumper->registerFilter('javascript', new JavaScriptFilter());

$parser = new Parser(new Lexer());
$jade = new Jade($parser, $dumper);

$requestURI = explode('/',$_SERVER['REQUEST_URI']);
$scriptName = explode('/',$_SERVER['SCRIPT_NAME']);
for ($i=0;$i<sizeof($scriptName);$i++)
{
	if ($requestURI[$i] == $scriptName[$i])
		unset($requestURI[$i]);
}
$cmd = array_values($requestURI);


$template = __DIR__.'/views/index.jade';
echo $jade->render($template);
?>