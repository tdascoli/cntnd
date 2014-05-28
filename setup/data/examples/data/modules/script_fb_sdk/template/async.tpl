<!-- script_fb_sdk/template/async.tpl -->

{* The JavaScript SDK requires the fb-root element to be present in the page.

The fb-root element must not be hidden using display: none or visibility: hidden,
or some parts of the SDK will not work properly in Internet Explorer.

The SDK inserts elements into fb-root which expect to be positioned relative
to the body or relative to an element close to the top of the page.
It is best if the fb-root element is not inside of an element with
position: absolute or position: relative.

If you must place the fb-root element inside of a positioned element,
then you should also give it a position close to the top of the body
or some parts of the SDK may not work properly. *}
<div id="fb-root"></div>

<script type="text/javascript">

{literal}
window.fbAsyncInit = function() {
    // init the FB JS SDK
    FB.init({
{/literal}
{if 0 lt $channelUrl|trim|strlen}
        channelUrl : '{$channelUrl}',
{/if}
{if 0 lt $cookie|trim|strlen}
        cookie : {$cookie},
{/if}
{if 0 lt $kidDirectedSite|trim|strlen}
        kidDirectedSite : {$kidDirectedSite},
{/if}
{if 0 lt $status|trim|strlen}
        status : {$status},
{/if}
{if 0 lt $appId|trim|strlen}
        appId : '{$appId}',
{/if}
{if 0 lt $xfbml|trim|strlen}
        xfbml : {$xfbml}
{else}
        xfbml : false
{/if}
{literal}
    });
    // Additional initialization code such as adding Event Listeners goes here
};
{/literal}

// Load the SDK asynchronously
{literal}
(function(d, s, id) {
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) return;
    js = d.createElement(s);
    js.id = id;
    js.src = '//connect.facebook.net/{/literal}{$locale}{literal}/all.js';
    fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));
{/literal}
</script>

<!-- /script_fb_sdk/template/async.tpl -->
