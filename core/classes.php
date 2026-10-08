<?php
$appClasses = [
	//Core
	'AppServiceAPI' => '/usr/share/selmasu-simpli.travel/core/AppServiceAPI.php',
	'MenuBuilder' => '/usr/share/selmasu-simpli.travel/core/MenuBuilder.php',
	'Tables' => '/usr/share/selmasu-simpli.travel/core/Tables.php',

	//Core Pages

	//Core VOs
	'UserPermissions' => '/usr/share/selmasu-simpli.travel/core/valueObjects/UserPermissions.php',
    'Constants' => '/usr/share/selmasu-simpli.travel/core/valueObjects/Constants.php',
    'App' => '/usr/share/selmasu-simpli.travel/core/valueObjects/App.php',

	//Core Services
    'UserService' => '/usr/share/selmasu-simpli.travel/core/services/user/UserService.php',

	//Default
	'DefaultController' => '/usr/share/selmasu-simpli.travel/apps/default/views/default/DefaultController.php',
	'DefaultView' => '/usr/share/selmasu-simpli.travel/apps/default/views/default/DefaultView.php',

    //Trip
    'TripController' => '/usr/share/selmasu-simpli.travel/apps/trip/views/TripController.php',
    'TripView' => '/usr/share/selmasu-simpli.travel/apps/trip/views/TripView.php',
    'TripService' => '/usr/share/selmasu-simpli.travel/apps/trip/services/trip/TripService.php',
    'TripModel' => '/usr/share/selmasu-simpli.travel/apps/trip/services/trip/TripModel.php',
    'TripServiceAPI' => '/usr/share/selmasu-simpli.travel/apps/trip/services/trip/TripServiceAPI.php',
    'Trip' => '/usr/share/selmasu-simpli.travel/apps/trip/valueObjects/Trip.php',
    'Destination' => '/usr/share/selmasu-simpli.travel/apps/trip/valueObjects/Destination.php',
    'Day' => '/usr/share/selmasu-simpli.travel/apps/trip/valueObjects/Day.php',
    'Place' => '/usr/share/selmasu-simpli.travel/apps/trip/valueObjects/Place.php',
    'ToDo' => '/usr/share/selmasu-simpli.travel/apps/trip/valueObjects/ToDo.php',
    'Item' => '/usr/share/selmasu-simpli.travel/apps/trip/valueObjects/Item.php',
    'FellowTraveller' => '/usr/share/selmasu-simpli.travel/apps/trip/valueObjects/FellowTraveller.php',

    //Post
    'PostService' => '/usr/share/selmasu-simpli.travel/apps/post/services/post/PostService.php',
    'PostModel' => '/usr/share/selmasu-simpli.travel/apps/post/services/post/PostModel.php',
    'PostServiceAPI' => '/usr/share/selmasu-simpli.travel/apps/post/services/post/PostServiceAPI.php',
    'Post' => '/usr/share/selmasu-simpli.travel/apps/post/valueObjects/Post.php',
    'PostMedia' => '/usr/share/selmasu-simpli.travel/apps/post/valueObjects/PostMedia.php',
    'Comment' => '/usr/share/selmasu-simpli.travel/apps/post/valueObjects/Comment.php',

    //Users
    'UsersController' => '/usr/share/selmasu-simpli.travel/apps/users/UsersController.php',
    'UserListController' => '/usr/share/selmasu-simpli.travel/apps/users/views/userList/UserListController.php',
    'UserListView' => '/usr/share/selmasu-simpli.travel/apps/users/views/userList/UserListView.php',
    'UserFormController' => '/usr/share/selmasu-simpli.travel/apps/users/views/userForm/UserFormController.php',
    'UserFormView' => '/usr/share/selmasu-simpli.travel/apps/users/views/userForm/UserFormView.php',
    'UsersPermissions' => '/usr/share/selmasu-simpli.travel/apps/users/UsersPermissions.php',
    'UsersServiceAPI' => '/usr/share/selmasu-simpli.travel/apps/users/services/users/UsersServiceAPI.php',
    'UsersService' => '/usr/share/selmasu-simpli.travel/apps/users/services/users/UsersService.php',
    'UsersModel' => '/usr/share/selmasu-simpli.travel/apps/users/services/users/UsersModel.php',
];