<?php namespace Pyro\Module\Users\Model; 

use Cartalyst\Sentry\Users\Eloquent\User as EloquentUser;

/**
 * User model for the users module.
 * 
 * @author      PyroCMS Dev Team
 * @package     PyroCMS\Core\Modules\User\Models
 */
class User extends EloquentUser
{
    /**
     * Define the table name
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that aren't mass assignable
     *
     * @var array
     */
    protected $guarded = array();

    /**
     * Disable updated_at and created_at on table
     *
     * @var boolean
     */
    public $timestamps = false;

	/**
	 * Returns the relationship between users and groups.
	 *
	 * @return Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function groups()
	{
		return $this->belongsToMany('Pyro\Module\Users\Model\Group', 'users_groups', 'user_id');
	}

	public function getHidden()
	{
		array_unshift($this->hidden, 'salt');

		return $this->hidden;
	}

    /**
     * Returns the relationship between comments and users
     *
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function profile()
    {
        return $this->hasOne('Pyro\Module\Users\Model\Profile');
    }

	/**
	 * Find a user based from their username
	 *
	 * @param 	array $username Username of the user
	 * @return  $this
	 */
	public static function findByUsername($username)
	{
		return self::whereRaw('LOWER(username) = ?', array(strtolower($username)))->first();
	}

	/**
	 * Find a user based from their email
	 *
	 * @param 	array $username Username of the user
	 * @return  $this
	 */
	public static function findByEmail($email)
	{
		return self::whereRaw('LOWER(email) = ?', array(strtolower($email)))->first();
	}

	/**
	 * Finds a user by the login value.
	 *
	 * @param  string  $login
	 * @return Cartalyst\Sentry\Users\UserInterface
	 * @throws Cartalyst\Sentry\Users\UserNotFoundException
	 */
	public function findByLogin($login)
	{
		$user = $this->findByUsername($login) ?: $this->findByEmail($login);

		if (! $user) {
			throw new UserNotFoundException("A user could not be found with a login value of [$login].");
		}

		return $user;
	}

	/**
	 * Get recent users
	 *
	 * @return     array
	 */
	public function getRecent()
	{
		return $this
			->orderBy('created_on', 'desc')
			->all();
	}

	/**
	 * Get all user objects
	 *
	 * @return object
	 */
	public function getAll()
	{
		return $this
			->with('profiles')
			->groupBy('users.id')
			->all();
	}

	/**
	 * Get Stream Fields
	 *
	 * @TODO KILL ME! This should be a real join or something
	 * @return array
	 */
	public function getStreamFields()
	{
		return array();
		
		$this->stream = ci()->streams_m->get_stream('profiles', true, 'users');

		return ci()->streams_m->get_stream_fields($this->stream->id);
	}

	/**
	 * Checks if the user is a super user - has
	 * access to everything regardless of permissions.
	 *
	 * @return bool
	 */
	public function isAdmin()
	{
		$permissions = $this->getMergedPermissions();

		if ( ! array_key_exists('admin', $permissions))
		{
			return false;
		}

		return $permissions['admin'] == 1;
	}

	public function isSuperUser()
	{
		return $this->isAdmin();
	}

	/**
	 * Check if user is activated
	 *
	 * @return bool
	 */
	public function isActivated()
	{
		return (bool) $this->is_activated;
	}

	/**
	 * Update the last login time
	 */
	public function updateLastLogin()
	{
		$this->last_login = time();
		$this->save();
	}

	/**
	 * Activate a newly created user
	 *
	 * @param int $id
	 *
	 * @return bool
	 */
	public function activateUser()
	{
		$this->is_activated = true;
		$this->activation_code = null;
		$this->save();
	}

	public static function assignGroupIdsToUser(User $user = null, $group_ids = array())
    {
        if ( ! $user->isSuperUser() and ! empty($group_ids) and $groups = Group::findManyInId($group_ids))
        {
            foreach ($groups as $group)
            {
                // Add the groups to the user
                // We must pass a Group model to addGroup()
                $user->addGroup($group);
            }

            // Remove any groups that are not selected
            foreach ($user->groups as $group)
            {
                if ( ! in_array($group->id, $groups->modelKeys()))
                {
                    $user->removeGroup($group);
                }
            }
        }
    }

}