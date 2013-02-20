<?
  /*
    This file is part of BotQueue.

    BotQueue is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    BotQueue is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with BotQueue.  If not, see <http://www.gnu.org/licenses/>.
  */

	class APIV1Controller extends Controller
	{
		public static $api_version = "0.1";
		
		public function home()
		{
		  $this->setTitle("API Documentation - v" . self::$api_version);
		}
		
		public function endpoint()
		{
			$provider = new MyOAuthProvider();

			//we need to disable a check if it is our first call to requesttoken.
			$c = strtolower($this->args('api_call'));
			if ($c == 'requesttoken')
			{
				$provider->oauth->isRequestTokenEndpoint(true);
				$this->set('provider', $provider);
			}
			//accesstoken also needs the class.
			elseif ($c == 'accesstoken')
				$this->set('provider', $provider);

			try
			{
				$provider->oauth->checkOAuthRequest();

				$calls = array(
					'requesttoken',       //ok
					'accesstoken',        //ok
					'listqueues',         //ok
					'queueinfo',          //ok
					'createqueue',        //ok
					'listjobs',           //ok
					'jobinfo',            //ok
					'grabjob',            //ok
					'grabslicejob',       //ok
					'findnewjob',         //ok
					'dropjob',            //ok
					'canceljob',          //ok
					'completejob',        //ok
					'completeslicejob',   //ok
					'downloadedjob',      //ok
					'createjob',          //ok
					'updatejobprogress',  //ok
					'listbots',           //ok
					'botinfo',            //ok
					'registerbot',        //ok
					'updatebot',          //ok
					'updateslicejob',     //ok
				);
				if (in_array($c, $calls))
				{
					$fname = "api_{$c}";
					$data = $this->$fname();
				}
				else
					throw new Exception("Specified api_call '{$c}' does not exist.");
				
				$result = array('status' => 'success', 'data' => $data);
			}
			catch(Exception $e)
			{
				$result = array('status' => 'error', 'error' => $e->getMessage());
			}

			//add in our version.
			$result['_api_version'] = self::$api_version;
			
			echo JSON::encode($result);
				
			exit;
		}
		
		public function api_requesttoken()
		{
			//pull in our interface class.
			$provider = $this->get('provider');
			
			//this is where we generate our token.
			$token_key = MyOAuthProvider::generateToken();
			$token_secret = MyOAuthProvider::generateToken();

			//okay, save it to the db.
			$t = new OAuthToken();
			$t->set('type', 1);
			$t->set('consumer_id', $provider->consumer->id);
			$t->set('token', $token_key);
			$t->set('token_secret', $token_secret);
			$t->save();
	
			$data['oauth_token'] = $token_key;
			$data['oauth_token_secret'] = $token_secret;

			return $data;
		}

		public function api_accesstoken()
		{
			//pull in our interface class.
			$provider = $this->get('provider');
			
			$token = OAuthToken::findByKey($provider->oauth->token);
			$token->changeToAccessToken();
			
			$data['oauth_token'] = $token->get('token');
			$data['oauth_token_secret'] = $token->get('token_secret');

			return $data;
		}

		public function api_listqueues()
		{
			$data = array();
			$qs = User::$me->getQueues()->getRange(0, 100);
			if (!empty($qs))
				foreach ($qs AS $row)
					$data[] = $row['Queue']->getAPIData();

			return $data;
		}

		public function api_queueinfo()
		{
			if ($this->args('queue_id'))
				$queue = new Queue($this->args('queue_id'));
			else
				$queue = User::$me->getDefaultQueue();
				
			if (!$queue->isHydrated())
				throw new Exception("Could not find a queue.");
			
			$data = $queue->getAPIData();

			return $data;
		}
		
		public function api_createqueue()
		{
			if (!$this->args('name'))
				throw new Exception('Queue name is a required parameter.');

			$q = new Queue();
			$q->set('name', $this->args('name'));
			$q->set('user_id', User::$me->id);
			$q->save();

			Activity::log("created a queue named " . $q->getLink() . " via the API.");
			
			return $q->getAPIData();
		}

		public function api_createjob()
		{
			$queue = new Queue($this->args('queue_id'));
			if (!$queue->isHydrated())
			  $queue = User::$me->getDefaultQueue();
			  
			if (!$queue->isHydrated())
				throw new Exception("Could not find a queue.");
			if (!$queue->isMine())
				throw new Exception("This is not your queue.");
				
			//get our quantity and make sure its at least 1.
			if ($this->args('quantity'))
				$quantity = (int)$this->args('quantity');
			$quantity = max(1, $quantity);
			$quantity = min(100, $quantity);
			
			// there are 3 ways to create a job:
			// #1 - existing job id
			if ($this->args('job_id'))
			{
				$oldjob = new Job($this->args('job_id'));

				if (!$oldjob->isHydrated())
					throw new Exception("Job does not exist.");
				if (!$oldjob->getQueue()->isMine())
					throw new Exception("This job is not in your queue.");

        $file = $oldjob->getSourceFile();
        if (!$file->isHydrated())
				  $file = $oldjob->getFile();
				
				if (!$file->isHydrated())
					throw new Exception("No file found!");
				
				$jobs = $queue->addFile($file, $quantity);
			}
			// #2 - send a file url and we'll grab it.
			else if ($this->args('job_url'))
			{
			  //download our file.
			  $url = $this->args('job_url');
        $data = Utility::downloadUrl($url);

        //does it match?
        if (!preg_match("/\.(stl|obj|amf|gcode)$/i", $data['realname']))
          throw new Exception("The file <a href=\"$url\">{$data[realname]}</a> is not valid for printing.");
          
        //create our file object.
        $s3 = new S3File();
        $s3->set('user_id', User::$me->id);
        $s3->set('source_url', $url);
        $s3->uploadFile($data['localpath'], S3File::getNiceDir($data['realname']));

        //okay, create our jobs.
				$jobs = $queue->addFile($s3, $quantity);
			}
			// #3 - post a file via http multipart form
			else if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name']))
			{
        //upload our file to S3
        $file = $_FILES['file'];
        if ($file['error'] != 0)
        {
          if($file['size'] == 0 && $file['error'] == 0)
            $file['error'] = 5; 

          $upload_errors = array( 
            UPLOAD_ERR_OK        => "No errors.", 
            UPLOAD_ERR_INI_SIZE    => "Larger than upload_max_filesize.", 
            UPLOAD_ERR_FORM_SIZE    => "Larger than form MAX_FILE_SIZE.", 
            UPLOAD_ERR_PARTIAL    => "Partial upload.", 
            UPLOAD_ERR_NO_FILE        => "No file.", 
            UPLOAD_ERR_NO_TMP_DIR    => "No temporary directory.", 
            UPLOAD_ERR_CANT_WRITE    => "Can't write to disk.", 
            UPLOAD_ERR_EXTENSION     => "File upload stopped by extension.", 
            UPLOAD_ERR_EMPTY        => "File is empty." // add this to avoid an offset 
          );

          throw new Exception("File upload failed: " . $upload_errors[$file['error']]);
        }
        
        //does it match?
        if (!preg_match("/\.(stl|obj|amf|gcode)$/i", $file['name']))
          throw new Exception("The file '$file[name]' is not valid for printing.");

        //okay, we're good.. do it.
        $s3 = new S3File();
        $s3->set('user_id', User::$me->id);
        $s3->uploadFile($file['tmp_name'], S3File::getNiceDir($file['name']));
        
        //okay, create our jobs.
				$jobs = $queue->addFile($s3, $quantity);
      }
			else
			{
				throw new Exception("Unknown job creation method.");
			}

			Activity::log("created " . count($jobs) . " new " . Utility::pluralizeWord('job', count($jobs)) . " via the API.");
			
			$data = array();
			if (!empty($jobs))
				foreach($jobs AS $job)
					$data[] = $job->getAPIData();
					
			return $data;
		}

		public function api_listjobs()
		{
			if ($this->args('queue_id'))
				$queue = new Queue($this->args('queue_id'));
			else
				$queue = User::$me->getDefaultQueue();
				
			if (!$queue->isHydrated())
				throw new Exception("Could not find a queue.");
			
			$data = array();
			
			if ($this->args('status'))
				$col = $queue->getJobs($this->args('status'));
			else
				$col = $queue->getJobs();
			
			$jobs = $col->getRange(0, 50);
			if (!empty($jobs))
				foreach ($jobs AS $row)
					$data[] = $row['Job']->getAPIData();

			return $data;
		}
		
		public function api_grabjob()
		{
			$job = new Job($this->args('job_id'));
			if (!$job->isHydrated())
				throw new Exception("Job does not exist.");

			if (!$job->getQueue()->isMine())
				throw new Exception("This job is not in your queue.");
			
			$bot = new Bot($this->args('bot_id'));
			if (!$bot->isHydrated())
				throw new Exception("Bot does not exist.");
				
			if (!$bot->isMine())
				throw new Exception("This is not your bot.");
			
			if (!$bot->canGrab($job))
				throw new Exception("You cannot grab this job.");
			
			//attempt to grab our job.  will throw exceptions on failure.
			$job = $bot->grabJob($job, (bool)$this->args('can_slice'));

			//okay, do we slice it?
			if ($job->get('status') == 'slicing' && $this->args('can_slice'))
			  $job->getSliceJob()->grab($this->args('_uid'));
			
			//return the bot data w/ all our info.
			$bot = new Bot($bot->id);
			$data = $bot->getAPIData();

			Activity::log($bot->getLink() . " bot grabbed the " . $job->getLink() . " job via the API.");
			
			return $data;
		}

		public function api_grabslicejob()
		{
			$sj = new SliceJob($this->args('job_id'));
			if (!$sj->isHydrated())
				throw new Exception("Slice job does not exist.");

			if ($sj->get('user_id') != User::$me->id && User::$me->isAdmin())
				throw new Exception("This slice job is not yours to grab.");
				
			if (!$sj->get('status') != 'available')
				throw new Exception("You cannot grab this job.");
			
			//attempt to grab our job.  will throw exceptions on failure.
			$sj->grab($this->args('_uid'));
			
			//return the bot data w/ all our info.
			$data = $sj->getAPIData();

			Activity::log($bot->getLink() . " bot grabbed the " . $job->getLink() . " slice job via the API.");
			
			return $data;
		}
		
		public function api_dropjob()
		{
			$job = new Job($this->args('job_id'));
			if (!$job->isHydrated())
				throw new Exception("Job does not exist.");
			
			$bot = $job->getBot();
			if (!$bot->isHydrated())
				throw new Exception("Bot does not exist.");
				
			if (!$bot->isMine())
				throw new Exception("This is not your bot.");
			
			if (!$job->getQueue()->isMine())
				throw new Exception("This job is not in your queue.");
				
			if (!$bot->canDrop($job))
				throw new Exception("You cannot drop this job.");
			
			//do we need to log this?
			$error = $this->args('error');
			if ($error)
			  $job->logError($error);
			
			//okay, drop it now.
			$bot->dropJob($job);

			Activity::log($bot->getLink() . " bot dropped the " . $job->getLink() . " job via the API.");
			
			$data['job'] = $job->getAPIData();
			$data['bot'] = $bot->getAPIData();
			
			return $data;
		}
		
		public function api_canceljob()
		{
			$job = new Job($this->args('job_id'));
			if (!$job->isHydrated())
				throw new Exception("Job does not exist.");

			if (!$job->getQueue()->isMine())
				throw new Exception("This job is not in your queue.");

			if (!$job->canDelete($job))
				throw new Exception("You cannot delete this job.");

			Activity::log("cancelled the <strong>" . $job->getName() . "</strong> job via the API.");
				
			$job->cancelJob();

			return $job->getAPIData();
		}

		public function api_completejob()
		{
			$job = new Job($this->args('job_id'));
			if (!$job->isHydrated())
				throw new Exception("Job does not exist.");
			
			$bot = $job->getBot();
			if (!$bot->isHydrated())
				throw new Exception("Bot does not exist.");
				
			if (!$bot->isMine())
				throw new Exception("This is not your bot.");
			
			if (!$job->getQueue()->isMine())
				throw new Exception("This job is not in your queue.");
				
			if (!$bot->canComplete($job))
				throw new Exception("You cannot complete this job.");
				
			$bot->completeJob($job);

			Activity::log($bot->getLink() . " bot completed the " . $job->getLink() . " job via the API.");
			
			$data['job'] = $job->getAPIData();
			$data['bot'] = $bot->getAPIData();
			
			return $data;
		}


		public function api_completeslicejob()
		{
			$sj = new SliceJob($this->args('slice_job_id'));
			if (!$sj->isHydrated())
				throw new Exception("Slice job does not exist.");
			
			if ($sj->get('worker_token') != $this->args("_uid"))
				throw new Exception("You cannot complete this slice job.");
				
			//okay, complete the job.
			$sj->complete();

			Activity::log($sj->getLink() . " slice job completed via the API.");
			
			$data = $sj->getAPIData();

			return $data;
		}
		
		public function api_downloadedjob()
		{
			$job = new Job($this->args('job_id'));
			if (!$job->isHydrated())
				throw new Exception("Job does not exist.");
			
			$bot = $job->getBot();
			if (!$bot->isHydrated())
				throw new Exception("Bot does not exist.");
				
			if (!$bot->isMine())
				throw new Exception("This is not your bot.");
			
			if (!$job->getQueue()->isMine())
				throw new Exception("This job is not in your queue.");
				
			$job->set('status', 'taken');
			$job->set('downloaded_time', date("Y-m-d H:i:s"));
			$job->set('progress', 0); // clear our download progress meter.
			$job->save();
			
			$bot->set('last_seen', date("Y-m-d H:i:s"));
			$bot->save();
			
			return $job->getAPIData();
		}
		
		public function api_updatejobprogress()
		{
			$job = new Job($this->args('job_id'));
			if (!$job->isHydrated())
				throw new Exception("Job does not exist.");
			
			$bot = $job->getBot();
			if (!$bot->isHydrated())
				throw new Exception("Bot does not exist.");
				
			if (!$bot->isMine())
				throw new Exception("This is not your bot.");
			
			if (!$job->getQueue()->isMine())
				throw new Exception("This job is not in your queue.");
				
			$job->set('progress', (float)$this->args('progress'));
			$job->save();
			
			$bot->set('last_seen', date("Y-m-d H:i:s"));
			$bot->save();
			
			return $job->getAPIData();
		}

		public function api_jobinfo()
		{
			$job = new Job($this->args('job_id'));
			if (!$job->isHydrated())
				throw new Exception("Job does not exist.");
			
			if (!$job->getQueue()->isMine())
				throw new Exception("This job is not in your queue.");
				
			return $job->getAPIData();
		}
		
		public function api_listbots()
		{
			$data = array();
			$bots = User::$me->getBots()->getRange(0, 100);
			if (!empty($bots))
				foreach ($bots AS $row)
					$data[] = $row['Bot']->getAPIData();

			return $data;
		}
		
		public function api_botinfo()
		{
			$bot = new Bot($this->args('bot_id'));
			if (!$bot->isHydrated())
				throw new Exception("Bot does not exist.");
			
			if (!$bot->isMine())
				throw new Exception("This bot is not yours.");
			
			//record our bot as having checked in.
			$bot->set('last_seen', date("Y-m-d H:i:s"));
			$bot->save();
			
			return $bot->getAPIData();
		}
		
		public function api_findnewjob()
		{
			$bot = new Bot($this->args('bot_id'));
			if (!$bot->isHydrated())
				throw new Exception("Bot does not exist.");
			
			if (!$bot->isMine())
				throw new Exception("This bot is not yours.");
				
			//can we slice?
			$can_slice = ($this->args('can_slice') && $bot->get('slice_engine_id') && $bot->get('slice_config_id'));

			//load up our data.
			$data = array();	
			$job = $bot->getQueue()->findNewJob($bot, $can_slice);
			if ($job->isHydrated())
				$data = $job->getAPIData();
			
			//record our bot as having checked in.
			$bot->set('last_seen', date("Y-m-d H:i:s"));
			$bot->save();
			
			return $data;			
		}

		public function api_registerbot()
		{
			if (!$this->args('name'))
				throw new Exception('Bot name is a required parameter.');
			if (!$this->args('identifier'))
				throw new Exception('Bot identifier is a required parameter.');
			#if (!$this->args('manufacturer'))
			#	throw new Exception('Bot manufacturer is a required parameter.');
			#if (!$this->args('model'))
			#	throw new Exception('Bot model is a required parameter.');
				
			$bot = new Bot();
			$bot->set('user_id', User::$me->id);
			$bot->set('name', $this->args('name'));
			$bot->set('identifier', $this->args('identifier'));
			$bot->set('manufacturer', $this->args('manufacturer'));
			$bot->set('model', $this->args('model'));
			$bot->set('electronics', $this->args('electronics'));
			$bot->set('firmware', $this->args('firmware'));
			$bot->set('extruder', $this->args('extruder'));
			$bot->set('status', 'idle');
			$bot->save();

			Activity::log("registered the new bot " . $bot->getLink() . " via the API.");
			
			return $bot->getAPIData();
		}
		
		public function api_updatebot()
		{
		  if (!$this->args('bot_id'))
		    throw new Exception("You must provide the 'bot_id' parameter.");
		    
			$bot = new Bot($this->args('bot_id'));
			if (!$bot->isHydrated())
				throw new Exception("Bot does not exist.");
			
			if (!$bot->isMine())
				throw new Exception("This bot is not yours.");

			//if (!$this->args('manufacturer'))
			//	throw new Exception('Bot manufacturer is a required parameter.');
			//if (!$this->args('model'))
			//	throw new Exception('Bot model is a required parameter.');
			
			if ($this->args('name'))	
  			$bot->set('name', $this->args('name'));
  		if ($this->args('name'))
  			$bot->set('identifier', $this->args('identifier'));
  		if ($this->args('manufacturer'))
  			$bot->set('manufacturer', $this->args('manufacturer'));
  		if ($this->args('model'))
  			$bot->set('model', $this->args('model'));
  		if ($this->args('electronics'))
  			$bot->set('electronics', $this->args('electronics'));
      if ($this->args('firmware'))
  			$bot->set('firmware', $this->args('firmware'));
  		if ($this->args('extruder'))
  			$bot->set('extruder', $this->args('extruder'));
  		if ($this->args('status'))
  		  $bot->set('status', $this->args('status'));
  		if ($this->args('error_text'))
  		  $bot->set('error_text', $this->args('error_text'));
			$bot->save();

			Activity::log("updated the bot " . $bot->getLink() . " via the API.");
			
			return $bot->getAPIData();
		}
		
		public function api_updateslicejob()
		{
	    if (!$this->args('job_id'))
		    throw new Exception("You must provide the 'bot_id' parameter.");
		    
			$sj = new SliceJob($this->args('job_id'));
			if (!$sj->isHydrated())
				throw new Exception("Slice job does not exist.");
			
			if ($sj->get('user_id') != User::$me->id)
				throw new Exception("This slice job is not yours.");

      //load up our objects
      $job = $sj->getJob();
      $bot = $job->getBot();

      //upload our file to S3
      $file = $_FILES['file'];
      if ($file['error'] != 0)
      {
        if($file['size'] == 0 && $file['error'] == 0)
          $file['error'] = 5; 

        $upload_errors = array( 
          UPLOAD_ERR_OK        => "No errors.", 
          UPLOAD_ERR_INI_SIZE    => "Larger than upload_max_filesize.", 
          UPLOAD_ERR_FORM_SIZE    => "Larger than form MAX_FILE_SIZE.", 
          UPLOAD_ERR_PARTIAL    => "Partial upload.", 
          UPLOAD_ERR_NO_FILE        => "No file.", 
          UPLOAD_ERR_NO_TMP_DIR    => "No temporary directory.", 
          UPLOAD_ERR_CANT_WRITE    => "Can't write to disk.", 
          UPLOAD_ERR_EXTENSION     => "File upload stopped by extension.", 
          UPLOAD_ERR_EMPTY        => "File is empty." // add this to avoid an offset 
        );
      
        throw new Exception("File upload failed: " . $upload_errors[$file['error']]);
      }
      
      //okay, we're good.. do it.
      $s3 = new S3File();
      $s3->set('user_id', User::$me->id);
      $s3->uploadFile($file['tmp_name'], S3File::getNiceDir($file['name']));

      //update our status.
      $sj->set('output_log', $this->args('output'));
      $sj->set('error_log', $this->args('errors'));
      $sj->set('output_id', $s3->id);
      $sj->save();
      
      //update our job
      $job->set('slice_complete_time', date("Y-m-d H:i:s"));
      $job->set('file_id', $s3->id);
      $job->save();
      
      //what do do with it now?
      if ($this->args('status') == 'complete')
        $sj->pass();
      else if ($this->args('status') == 'failure')
        $sj->fail();
      else if ($this->args('status') == 'pending')
      {
        $sj->set('status', 'pending');
        $sj->set('finish_date', date("Y-m-d H:i:s"));
        $sj->save();
      }
      
			Activity::log($bot->getLink() . " sliced the " . $job->getLink() . " job via the API.");
			
			return $job->getBot()->getAPIData();
		}
	}
?>