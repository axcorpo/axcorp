<?php

/* @var $this yii\web\View */

use frontend\models\AcceptCookiesForm;
use yii\web\View;

$settings = Yii::$app->settings->getAll();
?>

<?php // Google Analytics sets _ga cookies: only with the analytics category accepted. ?>
<?php if ($settings['seo']['googleAnalytics'] && AcceptCookiesForm::allows(AcceptCookiesForm::CATEGORY_ANALYTICS)) : ?>
	<script>
		(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

		ga('create', '<?= $settings['seo']['googleAnalytics'] ?>', 'auto');
		ga('send', 'pageview');
	</script>
<?php endif; ?>

<?php // The Facebook SDK (customer chat) sets Facebook cookies: only with marketing accepted. ?>
<?php if (($settings['socialNetwork']['facebookAppId'] || $settings['socialNetwork']['facebookPage']) && AcceptCookiesForm::allows(AcceptCookiesForm::CATEGORY_MARKETING)) : ?>
    <div id="fb-root"></div>
    <script>
        window.fbAsyncInit = function() {
            FB.init({
                xfbml            : true,
                version          : 'v6.0'
            });
        };

        (function(d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) return;
            js = d.createElement(s); js.id = id;
            js.src = 'https://connect.facebook.net/ro_RO/sdk/xfbml.customerchat.js';
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));</script>

    <?php if ($settings['socialNetwork']['facebookAppId'] && $settings['socialNetwork']['enableFacebookCustomerChat']): ?>
        <div class="fb-customerchat"
             attribution=setup_tool
             page_id="<?= $settings['socialNetwork']['facebookAppId'] ?>"
             greeting_dialog_display="hide">
        </div>
    <?php endif; ?>
<?php endif; ?>



<?php // The Facebook Pixel (and its noscript image) sets _fbp/fr cookies: only with marketing accepted. ?>
<?php if ($settings['socialNetwork']['facebookPixelId'] && AcceptCookiesForm::allows(AcceptCookiesForm::CATEGORY_MARKETING)) : ?>
	<script>
		!function(f,b,e,v,n,t,s)
		{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
			n.callMethod.apply(n,arguments):n.queue.push(arguments)};
			if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
			n.queue=[];t=b.createElement(e);t.async=!0;
			t.src=v;s=b.getElementsByTagName(e)[0];
			s.parentNode.insertBefore(t,s)}(window,document,'script',
			'https://connect.facebook.net/en_US/fbevents.js');
		fbq('init', '<?= $settings['socialNetwork']['facebookPixelId'] ?>');
		fbq('track', 'PageView');
	</script>
	<noscript>
		<img height="1" width="1" src="https://www.facebook.com/tr?id=<?= $settings['socialNetwork']['facebookPixelId'] ?>&ev=PageView&noscript=1"/>
	</noscript>
<?php endif; ?>

<?php if ($settings['general']['reCaptchaSiteKey']) : ?>
    <script src="https://www.google.com/recaptcha/api.js?onload=onloadCallback" async defer></script>
    <script>
        var onloadCallback = function() {
            // Only pages with a form widget (.g-recaptcha) have a client to execute; the
            // footer newsletter renders its own widget on submit.
            if (document.querySelector('.g-recaptcha')) {
                grecaptcha.execute();
            }
        };

        function setResponse(response) {
            document.getElementById('captcha-response').value = response;
        }
    </script>
<?php endif; ?>
