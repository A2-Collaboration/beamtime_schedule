<?php

//use \Exception;

class UsersController extends \BaseController {

	protected $user;

	public function __construct(User $user)
	{
		$this->user = $user;
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		if (Auth::guest())
			return Redirect::guest('login');

		$perPage = 50;
		// this will only work when the search string will be send as GET from the form because users.index is adressed as GET in the routes
		if (Input::has('search')) {
			$s = Input::get('search');
			$workgroups = Workgroup::where('name', 'LIKE', '%'.$s.'%')
				->orWhere('country', 'LIKE', '%'.$s.'%')
				->get()->lists('id');
			$users = $this->user->where('username', 'LIKE', '%'.$s.'%')
				->orWhere('first_name', 'LIKE', '%'.$s.'%')
				->orWhere('last_name', 'LIKE', '%'.$s.'%')
				->orWhereIn('workgroup_id', $workgroups);
			if (count($workgroups))
				$users = $users->orderBy('workgroup_id', 'asc')->paginate($perPage);
			else
				$users = $users->orderBy('last_name', 'asc')->paginate($perPage);
			$users->setBaseUrl('users');
			return View::make('users.index', ['users' => $users]);
		}

		//$users = $this->user->all();
		// use pagination instead
		if (Input::has('sort'))
			$users = $this->user->orderBy('last_name', Input::get('sort'))->paginate($perPage);
		else
			$users = $this->user->paginate($perPage);
		$users->setBaseUrl('users');

		return View::make('users.index', ['users' => $users])->withInput(Input::all());
	}

	//TODO delete? tried to combine sort and search, but it didn't work. added now an if statement to show only the sorting links when no search was done.
	public function index_test(){
		//return User::orderBy('username', 'asc')->get();
		//return User::orderBy('username', 'asc')->take(2)->get();  // only the first two that match

		if (Auth::guest())
			return Redirect::guest('login');

		$query = NULL;
		$sort = NULL;

		if (Input::has('sort')) {
			$sort = Input::get('sort');
			$query = array_add($query, 'sort', $sort);
		} else
			$sort = 'asc';

		// this will only work when the search string will be send as GET from the form because users.index is adressed as GET in the routes
		if (Input::has('search')) {
			$s = Input::get('search');
			$query = array_add($query, 'search', $s);
			$users = $this->user->where('username', 'LIKE', '%'.$s.'%')
				->orWhere('first_name', 'LIKE', '%'.$s.'%')
				->orWhere('last_name', 'LIKE', '%'.$s.'%')
				->orderBy('last_name', $sort)->paginate(20);
			return View::make('users.index', ['users' => $users]);
		} else
			$users = $this->user->paginate(20);

		//$users = $this->user->all();
		// use pagination instead
		/*if (Input::has('sort'))
			$users = $this->user->orderBy('username', Input::get('sort'))->paginate(20);
		else
			$users = $this->user->paginate(20);*/

		if ($query) {
			$queryString = http_build_query($query);
			URL::to('users?' . $queryString);
		}
		return View::make('users.index', ['users' => $users]);
}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		// a logged in user don't need to create an account, redirect to edit profile page
		if (Auth::check())
			return Redirect::to('users/'.Auth::user()->username.'/edit');

		$workgroups = array('' => 'Please select your workgroup') + Workgroup::orderBy('name', 'asc')->lists('name', 'id');

		return View::make('users.create', ['workgroups' => $workgroups]);
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		$input = Input::all();

		if (!$this->user->fill($input)->isValid())
			return Redirect::back()->withInput()->withErrors($this->user->errors);

		$this->user->password = Hash::make(Input::get('password'));
		$this->user->save();

		// if this is the first user, we set him as an admin and enable him by default
		if ($this->user->id == 1) {
			// set the value manually because they're guarded, user->update(['isAdmin' => true]) won't work due to mass assignment protection (via array)
			$this->user->toggleAdmin();
			$this->user->enable();
			$this->user->save();

			Auth::login($this->user);  // authenticate user

			return Redirect::to('')->with('success', 'Account created successfully. Set as Admin and enabled by default as it\'s the first user account.');
		}

		// send amdins a notification about the new registration
		$admins = User::where('role', '&', User::ADMIN)->get();
		// mail content
		$subject = 'New Account Registered';
		$msg = "Hello [USER],\r\n\r\n";
		$msg.= $this->user->get_full_name() . ' from ' . $this->user->workgroup->name . " has registered a new account for the Beamtime Scheduler.\r\n\r\n";
		$msg.= 'You can use the following link to view all non-enabled users: ' . url() . "/users/enable\r\n\r\n";
		$msg.= "A2 Beamtime Scheduler";
		$success = true;

		// send the mail to every user from the shift
		$admins->each(function($user) use(&$success, $subject, $msg)
		{
			$success &= $user->mail($subject, str_replace(array('[USER]'), array($user->first_name), $msg));
		});

		// added an enabled option, new users have first to get activated, return them to the homepage with an appropriate message
		return Redirect::to('')->with('success', 'Account created successfully. Please wait until your account gets activated by an Admin before you can login.');
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		if (Auth::guest())
			return Redirect::guest('login');

		$user = $this->user->whereUsername($id)->first();

		return View::make('users.show', ['user' => $user]);
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		if (Auth::guest())
			return Redirect::guest('login');

		$user = $this->user->whereUsername($id)->first();//User::find($id);
		$workgroups = Workgroup::orderBy('name', 'asc')->lists('name', 'id');//Workgroup::lists('name', 'id');

		return View::make('users.edit')->with('user', $user)->with('workgroups', $workgroups);
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		if (Auth::guest())
			return Redirect::guest('login');

		// allow only admin or the current user to edit the user information
		if (Auth::user()->isAdmin() || Auth::user()->id == $id) {
			$user = $this->user->whereId($id)->first();
			$data = array();
			$validator = NULL;
			/* Use the PATCH HTTP request for editing profile information and the PUT method for changing the password */
			if (Input::get('_method') === "PATCH") {
				// copy only field values to the data array which are allowed to be changed
				$data = array_only(Input::all(), array('first_name', 'last_name', 'email', 'workgroup_id', 'phone_institute', 'phone_private', 'phone_mobile', 'rating'));
				// get the defined rules for this action
				$rules = User::$rules_edit;
				// change the email rule because we sometimes want to "change" our email to the value which already exists in the database -> force excluding this by adding the id
				$rules['email'] = 'required|min:7|email|unique:users,email,'.$id;
				$validator = Validator::make($data, $rules);
				if ($validator->fails()){
					return Redirect::back()->withInput()->withErrors($validator);}
				$user->fill($data)->save();
				return Redirect::back()->with('success', 'Profile data edited successfully');
			} else if (Input::get('_method') === "PUT") {
				$data = array_only(Input::all(), array('password_old', 'password', 'password_confirmation'));
				$rules = User::$rules_pwChange;
				// check if the old password matches the current user's password hash
				if (!Hash::check($data['password_old'], $user->password))
					return Redirect::back()->with('error', 'Wrong password!');
				$validator = Validator::make($data, $rules);
				if ($validator->fails()){
					return Redirect::back()->withErrors($validator);}
				// if everything is fine, hash the new password
				$user->password = Hash::make($data['password']);
				$user->save();
				return Redirect::back()->with('success', 'Password changed successfully');
			} else
				return "Error 404, wrong HTTP request!";
		}

		return Redirect::back()->with('error', 'You are not allowed to edit this user');
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		if (Auth::guest())
			return Redirect::guest('login');

		$user = User::find($id);
		// store the username for the success message
		$name = $user->username;
		$user->delete();

		// redirect to the users overview
		return Redirect::to('/users')->with('success', 'User ' . $name . ' deleted successfully');
	}


	/**
	 * Show an overview of all shifts a user has taken.
	 *
	 * @param string $id
	 * @return Response
	 */
	public function shifts($id)
	{
		if (Auth::guest())
			return Redirect::guest('login');
		else if (Auth::user()->username !== $id) {
			$user = $this->user->whereUsername($id)->first();
			return View::make('users.show', ['user' => $user]);
		}

		$user = Auth::user();
		return View::make('users.shifts')->with('user', $user)->with('shifts', $user->shifts);
	}


	/**
	 * Return an iCalendar file including all shifts a user has taken.
	 *
	 * @param string $id
	 * @return ics file
	 */
	public function ics($id)
	{
		if (Auth::guest())
			return Redirect::guest('login');
		else if (Auth::user()->username !== $id) {
			$user = $this->user->whereUsername($id)->first();
			return View::make('users.show', ['user' => $user]);
		}

		date_default_timezone_set('Europe/Berlin');

		$user = Auth::user();
		$shifts = $user->shifts;

		$vCalendar = new \Eluceo\iCal\Component\Calendar("Shifts of " . $user->get_full_name());

		$shifts->each(function($shift) use(&$vCalendar)
		{
			$vEvent = new \Eluceo\iCal\Component\Event();
			$vEvent->setDtStart(new DateTime($shift->start));
			$vEvent->setDtEnd($shift->end());
			$vEvent->setUseTimezone(true);
			$vEvent->setSummary('Shift');
			$vEvent->setDescription("Shift in beamtime \"" . $shift->beamtime->name . "\"");
			$vEvent->setDescriptionHTML('<b>Shift</b> in beamtime "<a href="' . url() . "/beamtimes/$shift->beamtime_id" . '">' . $shift->beamtime->name . '</a>"');
			//$vEvent->setLocation("Institut für Kernphysik \nMainz \nGermany", 'A2 Counting Room', '49.991, 8.237');
			$vEvent->setLocation("Institut für Kernphysik \nMainz \nGermany");

			$vCalendar->addComponent($vEvent);
		});

		header('Content-Type: text/calendar; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $user->username . '_shifts.ics"');

		return $vCalendar->render();
	}


	/**
	 * Return an iCalendar file including all shifts a user has taken.
	 *
	 * @param string $id
	 * @return Response
	 */
	public function generate_ical($id)
	{
		if (Auth::guest())
			return Redirect::guest('login');
		else if (Auth::user()->username !== $id) {
			$user = $this->user->whereUsername($id)->first();
			return View::make('users.show', ['user' => $user]);
		}

		$user = Auth::user();
		$hash_id = hash('crc32', $user->id);
		$hash_user = hash('crc32', $user->username);
		$hash = $hash_id . $hash_user;

		$user->ical = $hash;
		$user->save();

		return Redirect::route('users.settings')->with('success', 'iCal link generated successfully: ' . url() . '/ical/' . $hash);
	}


	/**
	 * Return an iCalendar file including all shifts a user has taken.
	 *
	 * @param string $hash
	 * @return ics file
	 */
	public function ical($hash)
	{
		date_default_timezone_set('Europe/Berlin');

		$user = User::whereIcal($hash)->first();
		$shifts = $user->shifts;

		$vCalendar = new \Eluceo\iCal\Component\Calendar("Shifts of " . $user->get_full_name());

		$shifts->each(function($shift) use(&$vCalendar)
		{
			$vEvent = new \Eluceo\iCal\Component\Event();
			$vEvent->setDtStart(new DateTime($shift->start));
			$vEvent->setDtEnd($shift->end());
			$vEvent->setUseTimezone(true);
			$vEvent->setSummary('Shift');
			$vEvent->setDescription("Shift in beamtime \"" . $shift->beamtime->name . "\"");
			$vEvent->setDescriptionHTML('<b>Shift</b> in beamtime "<a href="' . url() . "/beamtimes/$shift->beamtime_id" . '">' . $shift->beamtime->name . '</a>"');
			//$vEvent->setLocation("Institut für Kernphysik \nMainz \nGermany", 'A2 Counting Room', '49.991, 8.237');
			$vEvent->setLocation("Institut für Kernphysik \nMainz \nGermany");

			$vCalendar->addComponent($vEvent);
		});

		return $vCalendar->render();
	}


	/**
	 * Renew Radiation Protection Instruction for the user.
	 *
	 * @param  int  $id
	 * @param  date $date
	 * @return Response
	 */
	public function renewRadiationInstruction($id)
	{
		if (Auth::guest())
			return Redirect::guest('login');

		$date = '';
		if (Input::has('date'))
			$date = Input::get('date');

		if (Auth::user()->isAdmin() || Auth::user()->isRadiationExpert() || (Auth::user()->isRunCoordinator() && Auth::user()->hasRadiationInstruction($date))) {
			$rad = new RadiationInstruction;
			$rad->user_id = $id;
			$rad->begin = new DateTime($date);
			$rad->renewed_by = Auth::user()->id;
			$rad->save();

			return Redirect::back()->with('success', 'Successfully extended Radiation Protection Instruction for ' . User::find($id)->get_full_name());
		} else
			return Redirect::back()->with('error', 'You are not allowed to extended the Radiation Protection Instruction');
	}


	/**
	 * Show a page of new registered users to enable them if logged-in user has admin privileges
	 *
	 * @return Response
	 */
	public function viewNew()
	{
		if (Auth::user()->isAdmin()) {
			$users = $this->user->where('role', '!&', User::ENABLED)->get();

			return View::make('users.enable', ['users' => $users]);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show an overview page of all user-related actions the logged-in user can do if he has admin or PI privileges
	 *
	 * @return Response
	 */
	public function manageUsers()
	{
		if (Auth::user()->isAdmin() || Auth::user()->isPI()) {
			return View::make('users.manage');
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show a page of all users where the admin flag can be toggled if logged-in user has admin privileges
	 *
	 * @return Response
	 */
	public function viewAdmins()
	{
		if (Auth::user()->isAdmin()) {
			// sort users first by the isAdmin attribute and afterwards alphabetically by their last name
			$users = $this->user->orderBy('role', 'desc')->orderBy('last_name', 'asc')->get();

			if (Input::has('sort'))
				$this->sort_collection($users, Input::get('sort'));

			return View::make('users.admins', ['users' => $users]);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show a page of all users where the run coordinator flag can be toggled if logged-in user has admin or PI privileges
	 *
	 * @return Response
	 */
	public function viewRunCoordinators()
	{
		if (Auth::user()->isAdmin() || Auth::user()->isPI()) {
			// sort users first by the isAdmin attribute and afterwards alphabetically by their last name
			$users = $this->user->orderBy('role', 'desc')->orderBy('last_name', 'asc')->get();

			if (Input::has('sort'))
				$this->sort_collection($users, Input::get('sort'));

			return View::make('users.run_coordinators', ['users' => $users]);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show a page of all users where the radiation expert flag can be toggled if logged-in user has admin privileges
	 *
	 * @return Response
	 */
	public function viewRadiationExperts()
	{
		if (Auth::user()->isAdmin()) {
			// sort users first by the isAdmin attribute and afterwards alphabetically by their last name
			$users = $this->user->orderBy('role', 'desc')->orderBy('last_name', 'asc')->get();

			if (Input::has('sort'))
				$this->sort_collection($users, Input::get('sort'));

			return View::make('users.radiation_experts', ['users' => $users]);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show a page of all users where the retirement status can be toggled if logged-in user has admin privileges
	 *
	 * @return Response
	 */
	public function viewRetirementStatus()
	{
		if (Auth::user()->isAdmin()) {
			// sort users first by the isAdmin attribute and afterwards alphabetically by their last name
			$users = $this->user->orderBy('retire_date', 'desc')->orderBy('last_name', 'asc')->get();

			if (Input::has('sort'))
				$this->sort_collection($users, Input::get('sort'));

			return View::make('users.retirement_status', ['users' => $users]);
		} else
			return Redirect::to('/users');
	}

	/**
	 * Show a page of all users where the start date can be managed if logged-in user has admin privileges
	 *
	 * @return Response
	 */
	public function viewStartDate()
	{
		if (Auth::user()->isAdmin()) {
			// sort users first by the isAdmin attribute and afterwards alphabetically by their last name
			$users = $this->user->orderBy('start_date', 'desc')->orderBy('last_name', 'asc')->get();

			if (Input::has('sort'))
				$this->sort_collection($users, Input::get('sort'));

			return View::make('users.start_date', ['users' => $users]);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show a page of all users where the principle investigator flag can be toggled if logged-in user has admin or PI privileges
	 *
	 * @return Response
	 */
	public function viewPrincipleInvestigators()
	{
		if (Auth::user()->isAdmin() || Auth::user()->isPI()) {
			// sort users first by the isAdmin attribute and afterwards alphabetically by their last name
			$users = $this->user->orderBy('role', 'desc')->orderBy('last_name', 'asc')->get();

			if (Input::has('sort'))
				$this->sort_collection($users, Input::get('sort'));

			return View::make('users.principle_investigators', ['users' => $users]);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show a page of all users where the author flag can be toggled if logged-in user has admin or PI privileges
	 *
	 * @return Response
	 */
	public function viewAuthors()
	{
		if (Auth::user()->isAdmin() || Auth::user()->isPI()) {
			// sort users first by the isAdmin attribute and afterwards alphabetically by their last name
			$users = $this->user->orderBy('role', 'desc')->orderBy('last_name', 'asc')->get();

			// PIs should only see the registered users of their own workgroup to modify the author status
			if (Auth::user()->isPI())
				$users = $users->filter(function($user)
				{
					return Auth::user()->workgroup_id === $user->workgroup_id;
				});

			if (Input::has('sort'))
				$this->sort_collection($users, Input::get('sort'));

			return View::make('users.authors', ['users' => $users]);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show a page with all users for which the currently logged in user is legitimated to renew the radiation protection instruction
	 *
	 * @return Response
	 */
	public function viewRadiationInstruction()
	{
		if (Auth::user()->isRadiationExpert() || (Auth::user()->isRunCoordinator() && Auth::user()->hasRadiationInstruction())) {
			if (Auth::user()->isRadiationExpert())
				$users = $this->user->get();
			else {
				$users = Auth::user()->rcshifts->reject(function($rcshift)  // get all run coordinator shifts for the logged in user
				{
					return new DateTime($rcshift->start) < new DateTime();  // reject all shifts in the past
				})
				->beamtime->unique()  // get the corresponding beamtimes
				->shifts->users->unique();  // get all users from these beamtimes
			}
			if (Input::has('sort'))
				$this->sort_collection($users, Input::get('sort'));
			else
				// sort the users by the date they got the last radiation instruction renewal
				$users->sortBy(function($user)
				{
					if ($user->radiation_instructions()->count())
						return strtotime($user->radiation_instructions()->orderBy('begin', 'desc')->first()->begin);  // convert date string to timestamp, otherwise the sorting is wrong sometimes
					else
						return 1;
				});

			return View::make('users.radiation')->with('users', $users);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Enable the user with specific id $id
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function enable($id)
	{
		if (Auth::user()->isAdmin()) {
			$user = $this->user->find($id);
			$user->enable();
			$user->save();

			// send the enabled user a mail
			$subject = 'Account enabled';
			$msg = 'Hello ' . $user->first_name . ",\r\n\r\n";
			$msg.= 'your account has been enabled. You should be able to login and subscribe to shifts now. Please check your account information: ' . url() . '/users/' . $user->username . "/edit\r\n\r\n";
			$msg.= "A2 Beamtime Scheduler";
			$success = $user->mail($subject, $msg);

			return Redirect::route('users.new', ['users' => $this->user->where('role', '!&', User::ENABLED)->get()])->with('success', 'User ' . $user->username . ' enabled successfully');
		} else
			return Redirect::to('/users');
	}


	/**
	 * Toggle amin flag for user with the id $id
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function toggleAdmin($id)
	{
		if (Auth::user()->isAdmin()) {
			$user = $this->user->find($id);
			$user->toggleAdmin();
			$user->save();

			$msg = 'User ' . $user->first_name . ' ' . $user->last_name;
			if ($user->isAdmin())
				$msg .= ' is now an admin';
			else
				$msg .= ' is no longer an admin';

			return Redirect::route('users.admins', ['users' => $this->user->all()])->with('success', $msg);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Toggle run coordinator flag for user with the id $id
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function toggleRunCoordinator($id)
	{
		if (Auth::user()->isAdmin() || Auth::user()->isPI()) {
			$user = $this->user->find($id);
			$user->toggleRunCoordinator();
			$user->save();

			$msg = 'User ' . $user->first_name . ' ' . $user->last_name;
			if ($user->isRunCoordinator())
				$msg .= ' is now a run coordinator';
			else
				$msg .= ' is no longer a run coordinator';

			return Redirect::route('users.run_coordinators', ['users' => $this->user->all()])->with('success', $msg);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Toggle radiation expert flag for user with the id $id
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function toggleRadiationExpert($id)
	{
		if (Auth::user()->isAdmin()) {
			$user = $this->user->find($id);
			$user->toggleRadiationExpert();
			$user->save();

			$msg = 'User ' . $user->get_full_name();
			if ($user->isRadiationExpert())
				$msg .= ' is now a radiation expert';
			else
				$msg .= ' is no longer a radiation expert';

			return Redirect::route('users.radiation_experts', ['users' => $this->user->all()])->with('success', $msg);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Toggle retirement status flag for user with the id $id
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function toggleRetirementStatus($id)
	{
		if (Auth::user()->isAdmin()) {
			$user = $this->user->find($id);
			$user->toggleRetired();
			if ($user->isRetired())
				$user->retire_date = new DateTime();
			else
				$user->retire_date = "0000-00-00 00:00:00";
			$user->save();

			$msg = 'User ' . $user->get_full_name();
			if ($user->isRetired())
				$msg .= ' is now retired';
			else
				$msg .= ' is no longer retired';

			return Redirect::route('users.retirement_status', ['users' => $this->user->all()])->with('success', $msg);
		} else
			return Redirect::to('/users');
	}


	/**
	 * set start Date of user.
	 *
	 * @param  int  $id
	 * @param  date $date
	 * @return Response
	 */
	public function setStartDate($id)
	{
		if(!(Auth::user()->isAdmin()))
			return Redirect::to('/users')->with('error', 'You are not allowed to set user Start Date');

		$user = $this->user->find($id);

		$date = '';
		if (Input::has('date'))
			$date = Input::get('date');
		else
			return Redirect::back()->with('error', 'Start date empty, please set a date');

		$user->timestamps = false;
		$user->start_date = new DateTime($date);
		$user->save();

		return Redirect::back()->with('success', 'Successfully set Start Date for ' . User::find($id)->get_full_name());
	}


	/**
	 * set Retirement Date of user.
	 *
	 * @param  int  $id
	 * @param  date $date
	 * @return Response
	 */
	public function setRetirementDate($id)
	{
		if(!(Auth::user()->isAdmin()))
			return Redirect::to('/users')->with('error', 'You are not allowed to set user Retirement Date');

		$user = $this->user->find($id);

		if (!Input::has('date'))
			return Redirect::back()->with('error', 'Retirement date empty, please set a date');

		$date = Input::get('date');

		$user->timestamps = false;
		$user->setRetired();
		$user->retire_date = new DateTime($date);
		$user->save();

		return Redirect::back()->with('success', 'Successfully set Retirement Date for ' . $user->get_full_name());
	}


	/**
	 * Toggle principle investigator for user with the id $id
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function togglePrincipleInvestigator($id)
	{
		if (Auth::user()->isAdmin() || Auth::user()->isPI()) {
			$user = $this->user->find($id);
			$user->togglePI();
			$user->save();

			$msg = 'User ' . $user->first_name . ' ' . $user->last_name;
			if ($user->isPI())
				$msg .= ' is now a principle investigator';
			else
				$msg .= ' is no longer a principle investigator';

			return Redirect::route('users.principle_investigators', ['users' => $this->user->all()])->with('success', $msg);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Toggle author for user with the id $id
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function toggleAuthor($id)
	{
		if (Auth::user()->isAdmin() || Auth::user()->isPI()) {
			$user = $this->user->find($id);

			// only allow PIs to change authors of their own workgroup
			if (Auth::user()->isPI() && Auth::user()->workgroup_id !== $user->workgroup_id)
				return Redirect::back()->with('error', 'You are not allowed to change authors of external workgroups');

			$user->toggleAuthor();
			$user->save();

			$msg = 'User ' . $user->first_name . ' ' . $user->last_name;
			if ($user->isAuthor())
				$msg .= ' is now an author';
			else
				$msg .= ' is no longer an author';

			return Redirect::route('users.authors', ['users' => $this->user->all()])->with('success', $msg);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show users without KPH account
	 *
	 * @return Response
	 */
	public function viewNonKPH()
	{
		if (Auth::user()->isAdmin()) {
			$users = $this->user->whereNull('ldap_id')->get();

			return View::make('users.non-kph', ['users' => $users]);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show a form with all users without KPH account
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function ViewMakeKPH($id = NULL)
	{
		if (Auth::user()->isAdmin()) {
			$none = false;
			$users = $this->user->whereNull('ldap_id')->get();
			if (!$users->count())
				$none = true;
			else {
				foreach ($users as $user)
					$user->name = $user->get_full_name() . ' (' . $user->username . ')';
				$users = $users->lists('name', 'id');
			}

			if ($id) {
				$user = $this->user->find($id);
				return View::make('users.kph', ['users' => $users, 'none' => $none])->with('selected', $user);
			}

			return View::make('users.kph', ['users' => $users, 'none' => $none]);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Change the account type for a user to a KPH account
	 *
	 * @return Response
	 */
	public function activateKPHaccount()
	{
		if (Auth::user()->isAdmin()) {
			$data = Input::all();
			$rules = User::$rulesKPH;
			$rules['username'] = 'required|unique:users,username,' . Input::get('user_id');
			$validator = Validator::make($data, $rules);
			if ($validator->fails())
				return Redirect::back()->withInput()->withErrors($validator);
			$user = $this->user->whereId(Input::get('user_id'))->first();

			// check if we can connect to the LDAP server
			$ld = new LDAP();  // Create an instance of the LDAP helper class
			$ldap = $ld->test_connection();

			if (!$ldap)
				return Redirect::back()->withInput()->with('error', "The KPH LDAP server is not available. You can't change the user's account type now.");

			// check if the username exists on the LDAP server
			if ($ld->user_exists($data['username']))
				$ldap_data = $ld->search_user($data['username']);
			else
				return Redirect::back()->withInput()->withErrors(array('username' => 'Username does not exist on LDAP server!'));

			// change the user's account to the KPH credentials and set the ldap_id accordingly
			$user->username = $data['username'];
			$user->ldap_id = $ldap_data['uidnumber'][0];
			$user->password = 'ldap';
			$user->save();

			$users = $this->user->whereNull('ldap_id')->get();

			return Redirect::to('/users/non-kph')->with('users', $users)->with('success', 'Account of ' . $user->get_full_name() . ' successfully changed to a KPH account.');
		} else
			return Redirect::to('/users');
	}


	/**
	 * Show a form to choose user accounts which should get merged
	 *
	 * @return Response
	 */
	public function merge()
	{
		// only admins are allowed to merge user accounts
		if (!Auth::user()->isAdmin())
			return Redirect::to('users');

		if (Input::has('sort'))
			$users = $this->user->orderBy('last_name', Input::get('sort'))->get();
		else
			$users = $this->user->all();

		return View::make('users.merge', ['users' => $users])->withInput(Input::all());
	}


	/**
	 * Merge the chosen user accounts
	 *
	 * @return Response
	 */
	public function mergeAccounts()
	{
		// only admins are allowed to merge user accounts
		if (!Auth::user()->isAdmin())
			return Redirect::to('users');

		$merge = Input::get('merge');
		if (count($merge) !== 2)
			return Redirect::back()->with('error', 'You have to choose two accounts in order to merge them!');

		$first = User::find($merge[0]);
		$second = User::find($merge[1]);
		if (Input::get('keep') === 'later' && strtotime($first->created_at) < strtotime($second->created_at)) {
			$first = User::find($merge[1]);
			$second = User::find($merge[0]);
		}

		// migrate the taken shifts
		foreach ($second->shifts as $shift) {
			$shift->users()->detach($second->id);
			$shift->users()->attach($first->id);
		}
		// migrate the run coordinator shifts
		foreach ($second->rcshifts as $shift) {
			$shift->user()->detach($second->id);
			$shift->user()->attach($first->id);
		}
		// migrate the radiation instructions
		foreach ($second->radiation_instructions()->getResults() as $rad) {
			$rad->user_id = $first->id;
			$rad->save();
		}

		$second->delete();

		// redirect to the account of the merged user
		return Redirect::to('users/' . $first->username)->with('success', 'User accounts successfully merged!');
	}


	/**
	 * Return view with settings like different styles which can be applied
	 *
	 * @return Response
	 */
	public function settings()
	{
		$style = Auth::user()->css;

		return View::make('users.settings', ['style' => $style]);
	}


	/**
	 * Update the user's settings
	 *
	 * @return Response
	 */
	public function updateSettings($style)
	{
		if (empty($style))
			return Redirect::back()->withInput()->withErrors(array('error' => 'No style chosen. Please choose a style which you want to use.'));

		$user = Auth::user();
		$user->css = $style;
		$user->save();

		return Redirect::back()->with('style', $style)->with('success', 'Style changed to ' . ucfirst($style) . ' successfully.');
	}


	/**
	 * Show all registered users with a link to change their password
	 *
	 * @return Response
	 */
	public function password()
	{
		// only admins are allowed to change passwords
		if (!Auth::user()->isAdmin())
			return Redirect::to('users');

		if (Input::has('sort'))
			$users = $this->user->orderBy('last_name', Input::get('sort'))->get();
		else
			$users = $this->user->all();

		return View::make('users.password', ['users' => $users])->withInput(Input::all());
	}


	/**
	 * Show a form with all users to change the password
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function viewPasswordChange($id = NULL)
	{
		if (Auth::user()->isAdmin()) {
			$users = $this->user->all();
			foreach ($users as $user)
				$user->name = $user->get_full_name() . ' (' . $user->username . ')';
			$users = $users->lists('name', 'id');

			if ($id) {
				$user = $this->user->find($id);
				return View::make('users.pw_change', ['users' => $users])->with('selected', $user);
			}

			return View::make('users.pw_change', ['users' => $users]);
		} else
			return Redirect::to('/users');
	}


	/**
	 * Changes the password of a user
	 * If $id is given, the password of the corresponding user will be set to 'ldap'
	 * to indicate the usage of the connected LDAP account password
	 *
	 * @param  int $id
	 * @return Response
	 */
	public function passwordChange($id = NULL)
	{
		if (Auth::user()->isAdmin()) {
			$ldap = false;  // variable to determine if ldap link should be used or not
			// first check if we came from the user edit view to link the account back
			// to the KPH account, using the LDAP password
			// in this case the $id is '{users}' like it's defined in the routes
			if ($id !== '{users}')
				$ldap = true;
			// if $id is NULL, we came from the pw_change view to change the password
			// the form includes the 'user_id' as an input field
			else if (Input::has('user_id'))
				$id = Input::get('user_id');
			// else: something went wrong and we do not have the user id, return back with an error
			else
				return Redirect::back()->with('error', 'Could not determine the user, something went wrong.');

			// now we should know if the LDAP password should be used, and the user id is stored in $id
			$user = $this->user->whereId($id)->first();
			if (!$user)
				return Redirect::back()->with('error', 'No user with id ' . $id . ' found');

			// the LDAP case is handled quick, let's do it
			if ($ldap) {
				$user->password = 'ldap';
				$user->save();
				return Redirect::back()->with('success', 'User account of ' . $user->get_full_name() . ' linked to the KPH account');
			}

			// otherwise check the provided data and change the password
			$data = array_only(Input::all(), array('password', 'password_confirmation'));
			$rules = array_only(User::$rules_pwChange, array('password', 'password_confirmation'));
			$validator = Validator::make($data, $rules);
			if ($validator->fails())
				return Redirect::back()->withErrors($validator);
			// if everything is fine, hash the new password and save it
			$user->password = Hash::make($data['password']);
			$user->save();

			return Redirect::back()->with('success', 'Password of ' . $user->get_full_name() . ' changed successfully');
		} else
			return Redirect::to('/users');
	}


	/**
	 * Send mail to users stored/flashed in the Session
	 * Method expects to get subject and content values to fill the mail
	 *
	 * @return Response
	 */
	public function mail()
	{
		// get the users who should receive the mail
		$users = Session::get('users');
		if (!$users->count())
			return Redirect::back()->with('error', 'No users found in the session. Nothing sent.');

		// check if subject and content are present
		if (!Input::has('subject'))
			return Redirect::back()->with('error', 'No subject given. Mailing cancelled.');
		if (!Input::has('content'))
			return Redirect::back()->with('error', 'No content given. Mailing cancelled.');

		// mail content
		$subject = Input::get('subject');
		// use json_encode to get properly replaced line breaks (CR and LF) from textarea input
		$msg = json_encode(Input::get('content'));
		// check if mailing worked
		$success = true;

		// send the mail to every user who should receive it and attach these users to the swap request
		$users->each(function($user) use(&$success, $subject, $msg)
		{
			$success &= $user->mail($subject, str_replace(array('[USER]'), array($user->first_name), $msg), Auth::user(), array(Auth::user()->email));
		});

		if ($success)
			return Redirect::back()->with('success', 'Email sent successfully to shift subscribers of the current beamtime.');
		else {
			return Redirect::back()->with('error', 'Email couldn\'t be sent, mailing error...');
		}
	}


	/**
	 * Sort a collection in a given order
	 *
	 * @param Collection $collection
	 * @param string $order
	 * @return sorted collection
	 */
	public function sort_collection($collection, $order)
	{
		if ($order === 'asc')
			$collection->sortBy(function($user)
			{
				return strtolower($user->last_name);
			});
		else
			$collection->sortByDesc(function($user)
			{
				return strtolower($user->last_name);
			});

		return $collection;
	}


}
