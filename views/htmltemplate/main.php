<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
		<? if ($title): ?>
			<title><?=strip_tags($title)?> // <?=RR_PROJECT_NAME?></title>
		<? else: ?>
			<title><?=RR_PROJECT_NAME?></title>
		<? endif ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="Zach Hoeken / BotQueue.com">

    <!-- Le HTML5 shim, for IE6-8 support of HTML elements -->
    <!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

    <!-- Le styles -->
    <link href="/bootstrap/2.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="/bootstrap/2.3.0/css/bootstrap-responsive.min.css" rel="stylesheet">
    <link href="/css/botqueue.css" rel="stylesheet">

    <!-- Le jquery -->
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
    <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/jquery-ui.min.js"></script>

		<? if (defined('GOOGLE_ANALYTICS_TRACKING_CODE')): ?>
		 <script type="text/javascript">
		   var _gaq = _gaq || [];
			  _gaq.push(['_setAccount', '<?=GOOGLE_ANALYTICS_TRACKING_CODE?>']);
			  _gaq.push(['_setDomainName', "<?=SITE_HOSTNAME?>"]);
	  		  _gaq.push(['_setAllowLinker', true]);
			  _gaq.push(['_trackPageview']);

		   (function() {
		     var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		     ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
		     var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
		   })();
		 </script>
		<? endif ?>
		<? if (!empty(Controller::$rssFeeds)): ?>
			<? foreach (Controller::$rssFeeds AS $feed): ?>
				<link rel="alternate" type="application/rss+xml" title="<?= RR_PROJECT_NAME ?> - <?=$feed['title']?>" href="<?=$feed['url']?>" />
			<? endforeach ?>
		<? endif ?>

		<? if (IS_DEV_SITE): ?>
			<style>
				body
				{
					background: #D3BECF;
					background-repeat: repeat-all;
				}
				
				div.container {
				  background: #fff;
				}
			</style>
		<? endif ?>
	  <?= Controller::$content_for["head"] ?>
  </head>
  <body class="preview" data-spy="scroll" data-target=".subnav" data-offset="50">
    <div class="container">

<!-- Navbar -->
<section id="navbar">
  <div class="navbar">
    <div class="navbar-inner">
      <div class="container" style="width: auto;">
        <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </a>
        <a class="brand" href="/">BotQueue</a>
        <div class="nav-collapse">
          <ul class="nav">
            <li><a href="/">Dashboard</a></li>
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Actions<b class="caret"></b></a>
              <ul class="dropdown-menu">
                <li><a href="/upload">Create Job</a></li>
                <li><a href="/bot/register">Register Bot</a></li>
                <li><a href="/queue/create">Create Queue</a></li>
              </ul>
            </li>
            <li><a href="/bots">Bots</a></li>
            <li><a href="/queues">Queues</a></li>
            <li><a href="/jobs">Jobs</a></li>
            <li><a href="/apps">App</a></li>
            <li><a href="/slicers">Slicers</a></li>
            <li><a href="/help">Help</a></li>
            <? if (User::isAdmin()): ?>
              <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">Admin<b class="caret"></b></a>
                <ul class="dropdown-menu">
                </ul>
              </li>
            <? endif ?>
          </ul>
          <ul class="nav pull-right">
            <li class="divider-vertical"></li>
            <li class="dropdown">
							<? if (User::isLoggedIn()): ?>
	              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Hello, <?=User::$me->getName()?> <b class="caret"></b></a>
	              <ul class="dropdown-menu">
	                <li><a href="/preferences">Preferences</a></li>
									<li class="divider"></li>
	                <li><a href="/logout">Log Out</a></li>
	              </ul>
							<? else: ?>
	 							<li><a href="/login" style="padding-left: 17px; background: transparent url('/img/lock_icon.png') no-repeat 0px center;">Log in</a></li>
	              <li><a href="/register">Sign up</a></li>
							<? endif ?>
            </li>
          </ul>
					<!-- 
          <form class="navbar-search pull-right" action="">
            <input type="text" class="search-query span2" placeholder="Search">
          </form>
					-->
        </div><!-- /.nav-collapse -->
      </div>
    </div><!-- /navbar-inner -->
  </div><!-- /navbar -->
</section>

<!-- Content -->
<section id="content">
  
  <div class="alert alert-info">
    <strong>Welcome to BotQueue v2.0!</strong> Please check out the <a href="http://www.hoektronics.com/2013/02/16/botqueue-v2-slic3r-integration/">blog entry about the new release</a> for more details.  TL;DR: Slic3r integration.
  </div>
  
	<? if ($title): ?>
	  <div class="page-header">
	    <h1><?=$title?></h1>
	  </div>
	<? endif ?>
	
  <!-- Headings & Paragraph Copy -->
	<div class="row">
	  <div class="span12">
			<?=$content?>
		</div>
	</div> <!-- end content -->

  <br/><br/>
	<div class="alert alert-info">
    <strong>Hey You!</strong> If you run into any problems, please <a href="https://github.com/Hoektronics/BotQueue/issues/new">report a bug</a>.  Make sure to include the <strong>bumblebee/info.log</strong> file if it is client-related.
  </div>

</section>

<!-- Footer -->
<hr>

<footer>
	<div class="row">
		<div class="span6">
			<h3>Connect</h3>
			<a href="http://www.hoektronics.com">Blog</a><br/>
			<a href="https://twitter.com/hoeken">Twitter</a><br/>
			<a href="irc://irc.freenode.net/botqueue">Freenode #BotQueue</a><br/>
			<a href="https://groups.google.com/d/forum/botqueue">Google Group</a><br/>
		</div>
		<div class="span6">
			<h3>Info</h3>
			Made by <a href="/about">Zach Hoeken and friends</a> especially for you.<br/>
			Software licensed under the <a href="http://www.gnu.org/copyleft/gpl.html">GPL v3.0</a>. Code at <a href="https://github.com/Hoektronics/BotQueue">GitHub</a>.<br/>
			&copy; <?= date("Y") ?> <a href="http://www.hoektronics.com"><?= COMPANY_NAME ?></a>. Powered by <a href="http://www.botqueue.com">BotQueue</a>.<br/>
			Page generated in <?= round(microtime(true) - START_TIME, 3) ?> seconds.			
		</div>
	</div>
	<br/>
</footer>

</div><!-- /container -->

  <!-- Le javascript -->
  <!-- Placed at the end of the document so the pages load faster -->
  <script src="/js/botqueue.js"></script>
  <script src="/bootstrap/2.3.0/js/bootstrap.js"></script>
  </body>
</html>