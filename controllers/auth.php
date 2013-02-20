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

	class AuthController extends Controller
	{
		public function logout()
		{
			if (User::isLoggedIn())
			{
				Activity::log("logged out.");

				//remove our token, if we got one.
				if ($_COOKIE['token'])
				{
					$data = unserialize(base64_decode($_COOKIE['token']));
					$token = Token::byToken($data['token']);
					$token->delete();
				}
			
				//unset specific variables.
		    setcookie('token', '', time()-420000, '/', SITE_HOSTNAME);
				unset($_SESSION['userid']);

				//nuke the session.
				if (isset($_COOKIE[session_name()]))
			    setcookie(session_name(), '', time()-420000, '/');

				session_unset();
				session_destroy();

				$this->forwardToUrl("/");
			}
		}
		
		public function forgotpass()
		{
			$this->setTitle("Retrieve Forgotten Password");
			
			if ($this->args('submit'))
			{
				$user = User::byEmail($this->args('email'));
				if ($user->isHydrated())
				{
					//give them a pass hash.
					$user->set('pass_reset_hash', sha1(mt_rand() . mt_rand() . mt_rand()));
					$user->save();
					
					$link = "http://" . SITE_HOSTNAME . $user->getUrl() . "/resetpass:" . $user->get('pass_reset_hash');
					$text = Controller::byName('email')->renderView('lost_pass', array('user' => $user, 'link' => $link));
					$html = Controller::byName('email')->renderView('lost_pass_html', array('user' => $user, 'link' => $link));

					Activity::log("forgot his/her password. :P", $user);

					$email = Email::queue($user, "Password Reset", $text, $html);
					$email->send();
					
					$this->set('status', "We have sent a reset password confirmation email to '" . $this->args('email') . "'.");
				}
				else
					$this->set('error', "We could not find an account with that email address.");
					
				$this->setArg('email');
			}
		}
	}
?>
