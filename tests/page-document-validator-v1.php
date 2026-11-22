<?php
declare(strict_types=1);
require __DIR__.'/../api/page-document-validator-v1.php';
$valid=['schema_version'=>1,'page'=>['title'=>'Test','slug'=>'test-page'],'settings'=>['max_width'=>'1200px','background'=>'#ffffff'],'sections'=>[['id'=>'s1','type'=>'section','props'=>['padding'=>24],'children'=>[['id'=>'h1','type'=>'heading','props'=>['text'=>'Hello','level'=>1]],['id'=>'p1','type'=>'paragraph','props'=>['text'=>'Text','align'=>'center']],['id'=>'b1','type'=>'button','props'=>['text'=>'Go','href'=>'/directory.html']]]]]];
function assert_test(bool $pass,string $name):void{if(!$pass){fwrite(STDERR,"FAIL: $name
");exit(1);}echo "PASS: $name
";}
assert_test(bh_validate_page_document_v1($valid)['ok'],'valid document');
$bad=$valid;$bad['sections']=array_fill(0,41,$valid['sections'][0]);assert_test(!bh_validate_page_document_v1($bad)['ok'],'section count limit');
$bad=$valid;$bad['sections'][0]['children']=array_fill(0,81,$valid['sections'][0]['children'][0]);assert_test(!bh_validate_page_document_v1($bad)['ok'],'child count limit');
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
$bad=$valid;$bad['sections'][0]['props']['background']='url(javascript:alert(1))';assert_test(!bh_validate_page_document_v1($bad)['ok'],'unsafe section background rejected');
$bad=$valid;$bad['sections'][0]['props']['padding']=97;assert_test(!bh_validate_page_document_v1($bad)['ok'],'section padding upper bound');
$bad=$valid;$bad['sections'][0]['props']['gap']=-1;assert_test(!bh_validate_page_document_v1($bad)['ok'],'section gap lower bound');
$validContainer=$valid;$validContainer['sections'][0]['props']=['background'=>'#f4f8f7','padding'=>32,'gap'=>20,'width'=>80,'min_height'=>240,'radius'=>16,'border_width'=>2,'border_color'=>'#3c8e96','align'=>'center','margin_bottom'=>24];assert_test(bh_validate_page_document_v1($validContainer)['ok'],'valid independent container layout properties');
$validMobile=$validContainer;$validMobile['sections'][0]['props']['mobile']=['width'=>100,'padding'=>16,'gap'=>12,'min_height'=>0,'radius'=>12,'border_width'=>0,'margin_bottom'=>16,'align'=>'center'];assert_test(bh_validate_page_document_v1($validMobile)['ok'],'valid mobile container overrides');
$badMobile=$validMobile;$badMobile['sections'][0]['props']['mobile']['width']=101;assert_test(!bh_validate_page_document_v1($badMobile)['ok'],'mobile width upper bound');
$badMobile=$validMobile;$badMobile['sections'][0]['props']['mobile']['padding']=97;assert_test(!bh_validate_page_document_v1($badMobile)['ok'],'mobile padding upper bound');
$badMobile=$validMobile;$badMobile['sections'][0]['props']['mobile']['evil']='x';assert_test(!bh_validate_page_document_v1($badMobile)['ok'],'unknown mobile property rejected');
$badMobile=$validMobile;$badMobile['sections'][0]['props']['mobile']['align']='stretch';assert_test(!bh_validate_page_document_v1($badMobile)['ok'],'invalid mobile alignment rejected');
$bad=$valid;$bad['sections'][0]['props']['width']=101;assert_test(!bh_validate_page_document_v1($bad)['ok'],'container width upper bound');
$bad=$valid;$bad['sections'][0]['props']['min_height']=801;assert_test(!bh_validate_page_document_v1($bad)['ok'],'container height upper bound');
$bad=$valid;$bad['sections'][0]['props']['border_color']='url(javascript:alert(1))';assert_test(!bh_validate_page_document_v1($bad)['ok'],'unsafe container border colour rejected');
$bad=$valid;$bad['sections'][0]['props']['align']='stretch';assert_test(!bh_validate_page_document_v1($bad)['ok'],'invalid container alignment rejected');
assert_test(bh_page_document_safe_href_v1('https://example.com'),'https URL');
assert_test(bh_page_document_safe_href_v1('mailto:test@example.com'),'mailto URL');
assert_test(bh_page_document_safe_href_v1('tel:+441234567890'),'tel URL');
assert_test(!bh_page_document_safe_href_v1('tel:abc'),'malformed tel URL rejected');
assert_test(!bh_page_document_safe_href_v1('mailto:not-an-email'),'malformed mailto URL rejected');
assert_test(!bh_page_document_safe_href_v1('/\\\\evil.test'),'backslash URL rejected');
assert_test(!bh_page_document_safe_href_v1('data:text/html,evil'),'data URL rejected');
echo "All validator tests passed.
";
