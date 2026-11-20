<?php
declare(strict_types=1);
require __DIR__.'/../api/page-document-validator-v1.php';
$valid=['schema_version'=>1,'page'=>['title'=>'Test','slug'=>'test-page'],'settings'=>['max_width'=>'1200px','background'=>'#ffffff'],'sections'=>[['id'=>'s1','type'=>'section','props'=>['padding'=>24],'children'=>[['id'=>'h1','type'=>'heading','props'=>['text'=>'Hello','level'=>1]],['id'=>'p1','type'=>'paragraph','props'=>['text'=>'Text','align'=>'center']],['id'=>'b1','type'=>'button','props'=>['text'=>'Go','href'=>'/directory.html']]]]]];
function assert_test(bool $pass,string $name):void{if(!$pass){fwrite(STDERR,"FAIL: $name
");exit(1);}echo "PASS: $name
";}
assert_test(bh_validate_page_document_v1($valid)['ok'],'valid document');
$bad=$valid;$bad['schema_version']=2;assert_test(!bh_validate_page_document_v1($bad)['ok'],'unsupported version');
$bad=$valid;$bad['sections'][0]['children'][0]['props']['level']=9;assert_test(!bh_validate_page_document_v1($bad)['ok'],'invalid heading level');
$bad=$valid;$bad['sections'][0]['children'][1]['id']='h1';assert_test(!bh_validate_page_document_v1($bad)['ok'],'duplicate ID');
$bad=$valid;$bad['sections'][0]['children'][2]['props']['href']='javascript:alert(1)';assert_test(!bh_validate_page_document_v1($bad)['ok'],'javascript URL rejected');
$bad=$valid;$bad['sections'][0]['children'][2]['props']['href']='//evil.test';assert_test(!bh_validate_page_document_v1($bad)['ok'],'protocol-relative URL rejected');
$bad=$valid;unset($bad['sections'][0]['children'][0]['props']['level']);assert_test(!bh_validate_page_document_v1($bad)['ok'],'missing heading level rejected');
$bad=$valid;unset($bad['sections'][0]['children'][1]['props']['text']);assert_test(!bh_validate_page_document_v1($bad)['ok'],'missing paragraph text rejected');
$bad=$valid;unset($bad['sections'][0]['children'][2]['props']['href']);assert_test(!bh_validate_page_document_v1($bad)['ok'],'missing button link rejected');
$bad=$valid;unset($bad['sections'][0]['children']);assert_test(!bh_validate_page_document_v1($bad)['ok'],'missing section children rejected');
$bad=$valid;$bad['sections'][0]['children'][0]['props']['align']='justify';assert_test(!bh_validate_page_document_v1($bad)['ok'],'invalid alignment rejected');
$bad=$valid;$bad['sections'][0]['evil']='x';assert_test(!bh_validate_page_document_v1($bad)['ok'],'unknown section property');
$bad=$valid;$bad['sections'][0]['children'][0]['props']['text']=str_repeat('x',501);assert_test(!bh_validate_page_document_v1($bad)['ok'],'heading text length');
assert_test(bh_page_document_safe_href_v1('https://example.com'),'https URL');
assert_test(bh_page_document_safe_href_v1('mailto:test@example.com'),'mailto URL');
assert_test(bh_page_document_safe_href_v1('tel:+441234567890'),'tel URL');
assert_test(!bh_page_document_safe_href_v1('tel:abc'),'malformed tel URL rejected');
assert_test(!bh_page_document_safe_href_v1('mailto:not-an-email'),'malformed mailto URL rejected');
assert_test(!bh_page_document_safe_href_v1('/\\\\evil.test'),'backslash URL rejected');
assert_test(!bh_page_document_safe_href_v1('data:text/html,evil'),'data URL rejected');
echo "All validator tests passed.
";
