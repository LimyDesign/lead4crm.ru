<?php
session_start();
require_once __DIR__.'/src/vendor/autoload.php';
$conf = json_decode(file_get_contents(__DIR__.'/config.json'), true);

$dumper = new PHPDumper();
$dumper->registerVisitor('tag', new AutotagsVisitor());
$dumper->registerFilter('javascript', new JavaScriptFilter());
$dumper->registerFilter('cdata', new CDATAFilter());
$dumper->registerFilter('php', new PHPFilter());
$dumper->registerFilter('style', new CSSFilter());

$parser = new Parser(new Lexer());
$jade = new Jade($parser, $dumper);

$template = __DIR__.'/views/index.jade';
echo $jade->render($template);
?>