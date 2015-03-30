var express = require('express');
var session = require('express-session');
var connect = require('connect');
var compress = require('compression');
var url = require('url');
var querystring = require('querystring');
var http = require('http');
var https = require('https');
var jade = require('jade');
var colors = require('colors');
var crypto = require('crypto');
var pg = require('pg');
var app = express();

var db = url.parse(process.env.DATABASE_URL);
var host = db.hostname;
var database = db.path.substring(1);
var port = db.port;
var db_userpass = db.auth.split(':');

var dbconfig = {
	host: host,
	port: port,
	database: database,
	user: db_userpass[0],
	password: db_userpass[1],
	ssl: true
};

var cabinet = false;

app.set('port', (process.env.PORT || 5000));
app.set('/views', __dirname + '/views');
app.use(compress());
app.use(express.static(__dirname + '/public'));
app.use(connect.cookieParser());

app.use(session({
	key: 'lead4crm',
	secret: "kksdjhfjgjhwehrfgvbksdhfgjfytrweyur",
	resave: false,
	saveUninitialized: true
}));

app.get('/', function(req, res) {
	if (req.session.authorized)
		cabinet = true;
	else
		cabinet = false;

	var oauth_state = crypto.createHmac('sha1', req.headers['user-agent'] + new Date().getTime()).digest('hex');
	req.session.oauth_state = oauth_state;

	var vklogin_query = querystring.stringify({
		client_id: process.env.VK_CLIENT_ID,
		scope: 'email',
		redirect_uri: 'http://' + req.headers.host + '/vklogin',
		response_type: 'code',
		v: '5.29',
		state: oauth_state,
		display: 'page'
	});
	var oklogin_query = querystring.stringify({
		client_id: process.env.OK_CLIENT_ID,
		scope: 'GET_EMAIL',
		response_type: 'code',
		redirect_uri: 'http://' + req.headers.host + '/oklogin',
		// layout: 'w',
		state: oauth_state
	});
	var fblogin_quey = querystring.stringify({
		client_id: process.env.FB_CLIENT_ID,
		scope: 'email',
		redirect_uri: 'http://' + req.headers.host + '/fblogin',
		response_type: 'code'
	});
	var gplogin_query = querystring.stringify({
		client_id: process.env.GP_CLIENT_ID,
		scope: 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
		redirect_uri: 'http://' + req.headers.host + '/gplogin',
		response_type: 'code',
		state: oauth_state,
		access_type: 'online',
		approval_prompt: 'auto',
		login_hint: 'email',
		include_granted_scopes: 'true'
	});
	var mrlogin_query = querystring.stringify({
		client_id: process.env.MR_CLIENT_ID,
		response_type: 'code',
		redirect_uri: 'http://' + req.headers.host + '/mrlogin'
	});
	var yalogin_query = querystring.stringify({
		response_type: 'code',
		client_id: process.env.YA_CLIENT_ID,
		state: oauth_state
	});
	var bxlogin_query = querystring.stringify({
		client_id: process.env.BX_CLIENT_ID,
		response_type: 'code',
		redirect_uri: 'http://' + req.headers.host + '/bxlogin'
	});
	res.render('index.jade', {
		title: 'Генератор лидов для Битрикс24',
		vklogin: 'https://oauth.vk.com/authorize?' + vklogin_query,
		oklogin: 'http://www.odnoklassniki.ru/oauth/authorize?' + oklogin_query,
		fblogin: 'https://www.facebook.com/dialog/oauth?' + fblogin_quey,
		gplogin: 'https://accounts.google.com/o/oauth2/auth?' + gplogin_query,
		mrlogin: 'https://connect.mail.ru/oauth/authorize?' + mrlogin_query,
		yalogin: 'https://oauth.yandex.ru/authorize?' + yalogin_query,
		// bxlogin: 'https://lsd.bitrix24.ru/oauth/authorize/?' + bxlogin_query,
		mainpage_url: 'http://' + req.headers.host,
		aboutproject_url: 'http://' + req.headers.host + '/about-project',
		aboutours_url: 'http://' + req.headers.host + '/about-ours',
		prices_url: 'http://' + req.headers.host + '/price',
		support_url: 'http://' + req.headers.host + '/support',
		cabinet_url: 'http://' + req.headers.host + '/cabinet',
		cabinet: cabinet,
		mainactive: true
	});
});

app.get('/fblogin', function(req, res) {
	console.log('Авторизация через соц.сеть "Facebook"'.green);

	var url_parts = url.parse(req.url, true);
	var query = url_parts.query;
	var data = querystring.stringify({
		client_id: process.env.FB_CLIENT_ID,
		client_secret: process.env.FB_CLIENT_SECRET,
		code: query.code,
		redirect_uri: 'http://' + req.headers.host + '/fblogin'
	});
	var options = {
		host: 'graph.facebook.com',
		port: 443,
		path: '/oauth/access_token?' + data,
		method: 'GET'
	};

	function async(arg, callback) {
		setTimeout(function() {
			console.log('Выполенение комманды ' + arg + '...');
			if (arg == 'httpsreq') {
				var httpsreq = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						callback('error');
					} else {
						res.on('data', function(chunk) {
							fb_res = querystring.parse(chunk);
						});
						callback(arg);
					}
				});
				httpsreq.end();
			} else if (arg == 'get_user_data') {
				options = {
					host: 'graph.facebook.com',
					port: 443,
					path: '/me?access_token=' + fb_res.access_token,
					method: 'GET'
				};
				var httpsreq2 = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						callback('error');
					} else {
						res.on('data', function(chunk) {
							console.log('FB 2th Response: ', chunk);
							fb_res2 = JSON.parse(chunk);
						});
						callback(arg);
					}
				});
				httpsreq2.end();
			} else if (arg == 'pgconnect') {
				var client = new pg.Client(dbconfig);
				client.connect(function(err) {
					if (err) {
						return console.error('Ошибка подключения к БД',err);
					}
					client.query('select * from users where fb = $1', [fb_res2.id], function(err, result) {
						if (err) {
							return console.error('Ошибка получения данных',err);
						} else {
							if (result.rows[0]) {
								console.log(result.rows[0]);
								req.session.authorized = true;
								req.session.userid = result.rows[0].id;
								req.session.user_email = result.rows[0].email;
								req.session.fb = result.rows[0].fb;
								client.end();
								callback(arg);
							} else {
								console.log('Попытка создания нового пользователя.');
								client.query("insert into users (email, fb) values ('" + fb_res2.email + "', " + fb_res2.id + ") returning id", function(err, result) {
									if (err) {
										return console.error('Ошибка записи данных в БД', err);
									} else {
										req.session.authorized = true;
										req.session.userid = result.rows[0].id;
										req.session.user_email = fb_res2.email;
										req.session.fb = fb_res2.id;
										console.log('Добавлен новый пользователь # ' + result.rows[0].id);
									}
									client.end();
									callback(arg);
								});
							}
						}
					});
				});
			}
		}, 4);
	}

	function final() {
		console.log('Готовчик!'.yellow, results);
		if (req.session.authorized && results.indexOf('error') < 0) {
			res.redirect('http://' + req.headers.host + '/cabinet');
		} else {
			res.redirect('http://' + req.headers.host);
		}
	}

	var fb_res;
	var fb_res2;
	var items = ["httpsreq", "get_user_data", "pgconnect"];
	var results = [];

	function series(item) {
		if (item) {
			async(item, function(result) {
				results.push(result);
				if (result == 'error')
					return final();
				else 
					return series(items.shift());
			});
		} else {
			return final();
		}
	}

	series(items.shift());
});

app.get('/vklogin', function(req, res) {
	console.log('Авторизация через соц.сеть "Вконтакте"'.green);

	var url_parts = url.parse(req.url, true);
	var query = url_parts.query;
	var data = querystring.stringify({
		client_id: process.env.VK_CLIENT_ID,
		client_secret: process.env.VK_CLIENT_SECRET,
		code: query.code,
		redirect_uri: 'http://' + req.headers.host + '/vklogin'
	});
	var options = {
		host: 'oauth.vk.com',
		port: 443,
		path: '/access_token?' + data,
		method: 'GET'
	};

	function async(arg, callback) {
		setTimeout(function() {
			console.log('Выполенение комманды ' + arg + '...');
			if (arg == 'httpsreq') {
				var httpsreq = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						callback('error');
					} else {
						res.on('data', function(chunk) {
							vk_res = JSON.parse(chunk);
						});
						callback(arg);
					}
				});
				httpsreq.end();
			} else if (arg == 'pgconnect') {
				var client = new pg.Client(dbconfig);
				client.connect(function(err) {
					if (err) {
						return console.error('Ошибка подключения к БД',err);
					}
					client.query('select * from users where vk = $1', [vk_res.user_id], function(err, result) {
						if (err) {
							return console.error('Ошибка получения данных',err);
						} else {
							if (result.rows[0]) {
								console.log(result.rows[0]);
								req.session.authorized = true;
								req.session.userid = result.rows[0].id;
								req.session.user_email = result.rows[0].email;
								req.session.vk = result.rows[0].vk;
								client.end();
								callback(arg);
							} else {
								console.log('Попытка создания нового пользователя.');
								client.query("insert into users (email, vk) values ('" + vk_res.email + "', " + vk_res.user_id + ") returning id", function(err, result) {
									if (err) {
										return console.error('Ошибка записи данных в БД', err);
									} else {
										req.session.authorized = true;
										req.session.userid = result.rows[0].id;
										req.session.user_email = vk_res.email;
										req.session.vk = vk_res.user_id;
										console.log('Добавлен новый пользователь # ' + result.rows[0].id);
									}
									client.end();
									callback(arg);
								});
							}
						}
					});
				});
			}
		}, 4);
	}

	function final() {
		console.log('Готовчик!'.yellow, results);
		if (req.session.authorized && results.indexOf('error') < 0) {
			res.redirect('http://' + req.headers.host + '/cabinet');
		} else {
			res.redirect('http://' + req.headers.host);
		}
	}

	var vk_res;
	var items = ["httpsreq","pgconnect"];
	var results = [];

	function series(item) {
		if (item) {
			async(item, function(result) {
				results.push(result);
				if (result == 'error')
					return final();
				else 
					return series(items.shift());
			});
		} else {
			return final();
		}
	}

	series(items.shift());
});

app.get('/oklogin', function(req, res) {
	console.log('Авторизация через соц.сеть "Одноклассники"'.green);

	var url_parts = url.parse(req.url, true);
	var query = url_parts.query;
	var data = querystring.stringify({
		client_id: process.env.OK_CLIENT_ID,
		client_secret: process.env.OK_SECRET_KEY,
		code: query.code,
		redirect_uri: 'http://' + req.headers.host + '/oklogin',
		grant_type: 'authorization_code'
	});
	var options = {
		host: 'api.odnoklassniki.ru',
		port: 443,
		path: '/oauth/token.do',
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Content-Length': data.length
		}
	};

	function async(arg, callback) {
		setTimeout(function() {
			console.log('Выполенение комманды ' + arg + '...');
			if (arg == 'httpsreq') {
				var httpsreq = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						callback('error');
					} else {
						res.on('data', function(chunk) {
							ok_res = JSON.parse(chunk);
						});
						callback(arg);
					}
				});
				httpsreq.write(data);
				httpsreq.end();
			} else if (arg == 'get_user_data') {
				var con_parameters = 'application_key=' + process.env.OK_PUBLIC_KEY + 'fields=uid,emailmethod=users.getCurrentUser';
				var ac_ask = ok_res.access_token + process.env.OK_SECRET_KEY;
				var md5_ac_ask = crypto.createHash('md5').update(ac_ask).digest('hex');
				var sig = con_parameters + md5_ac_ask;
				var md5_sig = crypto.createHash('md5').update(sig).digest('hex');
				data = querystring.stringify({
					application_key: process.env.OK_PUBLIC_KEY,
					method: 'users.getCurrentUser',
					access_token: ok_res.access_token,
					fields: 'uid,email',
					sig: md5_sig
				});
				options = {
					host: 'api.ok.ru',
					port: 80,
					path: '/fb.do?' + data,
					method: 'GET'
				};
				var httpreq = http.request(options, function(res) {
					res.setEncoding('utf8');
					res.on('data', function(chunk) {
						ok_res2 = JSON.parse(chunk);
					});
					if (res.statusCode != 200)
						callback('error');
					else
						callback(arg);
				});
				httpreq.end();
			} else if (arg == 'pgconnect') {
				var client = new pg.Client(dbconfig);
				client.connect(function(err) {
					if (err) {
						return console.error('Ошибка подключения к БД',err);
					}
					client.query('select * from users where ok = $1', [ok_res2.uid], function(err, result) {
						if (err) {
							return console.error('Ошибка получения данных',err);
						} else {
							if (result.rows[0]) {
								console.log(result.rows[0]);
								req.session.authorized = true;
								req.session.userid = result.rows[0].id;
								req.session.user_email = result.rows[0].email;
								req.session.ok = result.rows[0].ok;
								client.end();
								callback(arg);
							} else {
								console.log('Попытка создания нового пользователя. ');
								ok_res2.email = ok_res2.email || 'you@email.com';
								client.query("insert into users (email, ok) values ('" + ok_res2.email + "', " + ok_res2.uid + ") returning id", function(err, result) {
									if (err) {
										return console.error('Ошибка записи данных в БД', err);
									} else {
										req.session.authorized = true;
										req.session.userid = result.rows[0].id;
										req.session.user_email = ok_res2.email;
										req.session.ok = ok_res2.uid;
										console.log('Добавлен новый пользователь # ' + result.rows[0].id);
									}
									client.end();
									callback(arg);
								});
							}
						}
					});
				});
			}
		}, 4);
	}

	function final() {
		console.log('Готовчик!'.yellow, results);
		if (req.session.authorized && results.indexOf('error') < 0) {
			res.redirect('http://' + req.headers.host + '/cabinet');
		} else {
			res.redirect('http://' + req.headers.host);
		}
	}

	var ok_res;
	var ok_res2;
	var items = ["httpsreq","get_user_data","pgconnect"];
	var results = [];

	function series(item) {
		if (item) {
			async(item, function(result) {
				results.push(result);
				if (result == 'error')
					return final();
				else 
					return series(items.shift());
			});
		} else {
			return final();
		}
	}

	series(items.shift());
});

app.get('/gplogin', function(req, res) {
	console.log('Авторизация через сервис Google'.green);

	var url_parts = url.parse(req.url, true);
	var query = url_parts.query;
	var data = querystring.stringify({
		client_id: process.env.GP_CLIENT_ID,
		client_secret: process.env.GP_CLIENT_SECRET,
		code: query.code,
		redirect_uri: 'http://' + req.headers.host + '/gplogin',
		grant_type: 'authorization_code'
	});
	var options = {
		host: 'accounts.google.com',
		port: 443,
		path: '/o/oauth2/token',
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Content-Length': data.length
		}
	};

	function async(arg, callback) {
		setTimeout(function() {
			console.log('Выполенение комманды ' + arg + '...');
			if (arg == 'httpsreq') {
				var httpsreq = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						res.on('data', function(chunk) {
							console.log('Google Error Response:', chunk);
						});
						callback('error');
					} else {
						var _json = '';
						res.on('data', function(chunk) {
							_json += chunk.toString();
						});
						res.on('end', function() {
							gp_res = JSON.parse(_json);
							callback(arg);
						});
					}
				});
				httpsreq.write(data);
				httpsreq.end();
			} else if (arg == 'get_user_data') {
				options = {
					host: 'www.googleapis.com',
					port: 443,
					path: '/oauth2/v1/userinfo?' + querystring.stringify({ access_token: gp_res.access_token }),
					method: 'GET'
				};
				var httpsreq2 = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						callback('error');
					} else {
						res.on('data', function(chunk) {
							gp_res2 = JSON.parse(chunk);
						});
						callback(arg);
					}
				});
				httpsreq2.end();
			} else if (arg == 'pgconnect') {
				var client = new pg.Client(dbconfig);
				client.connect(function(err) {
					if (err) {
						return console.error('Ошибка подключения к БД',err);
					}
					client.query("select * from users where gp = $1", [gp_res2.id], function(err, result) {
						if (err) {
							return console.error('Ошибка получения данных',err);
						} else {
							if (result.rows[0]) {
								console.log(result.rows[0]);
								req.session.authorized = true;
								req.session.userid = result.rows[0].id;
								req.session.user_email = result.rows[0].email;
								req.session.gp = result.rows[0].gp;
								client.end();
								callback(arg);
							} else {
								console.log('Попытка создания нового пользователя. ');
								gp_res2.email = gp_res2.email || 'you@email.com';
								client.query("insert into users (email, gp) values ('" + gp_res2.email + "', " + gp_res2.id + ") returning id", function(err, result) {
									if (err) {
										return console.error('Ошибка записи данных в БД', err);
									} else {
										req.session.authorized = true;
										req.session.userid = result.rows[0].id;
										req.session.user_email = gp_res2.email;
										req.session.gp = gp_res2.id;
										console.log('Добавлен новый пользователь # ' + result.rows[0].id);
									}
									client.end();
									callback(arg);
								});
							}
						}
					});
				});
			}
		}, 4);
	}

	function final() {
		console.log('Готовчик!'.yellow, results);
		if (req.session.authorized && results.indexOf('error') < 0) {
			res.redirect('http://' + req.headers.host + '/cabinet');
		} else {
			res.redirect('http://' + req.headers.host);
		}
	}

	var gp_res;
	var gp_res2;
	var items = ["httpsreq","get_user_data","pgconnect"];
	var results = [];

	function series(item) {
		if (item) {
			async(item, function(result) {
				results.push(result);
				if (result == 'error')
					return final();
				else 
					return series(items.shift());
			});
		} else {
			return final();
		}
	}

	series(items.shift());
});

app.get('/mrlogin', function(req, res) {
	console.log('Авторизация через сервис Mail.Ru'.green);

	var url_parts = url.parse(req.url, true);
	var query = url_parts.query;
	var data = querystring.stringify({
		client_id: process.env.MR_CLIENT_ID,
		client_secret: process.env.MR_SECRET_KEY,
		code: query.code,
		redirect_uri: 'http://' + req.headers.host + '/mrlogin',
		grant_type: 'authorization_code'
	});
	var options = {
		host: 'connect.mail.ru',
		port: 443,
		path: '/oauth/token',
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Content-Length': data.length
		}
	};

	function async(arg, callback) {
		setTimeout(function() {
			console.log('Выполенение комманды ' + arg + '...');
			if (arg == 'httpsreq') {
				var httpsreq = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						callback('error');
					} else {
						var _json = '';
						res.on('data', function(chunk) {
							_json += chunk;
						});
						res.on('end', function() {
							mr_res = JSON.parse(_json);
						})
						callback(arg);
					}
				});
				httpsreq.write(data);
				httpsreq.end();
			} else if (arg == 'get_user_data') {
				console.log('Access Token:', mr_res.access_token);
				var sig = 'app_id=' + process.env.MR_CLIENT_ID + 'method=users.getInfo' + 
					'secure=1' + 'session_key=' + mr_res.access_token + process.env.MR_SECRET_KEY;
				console.log('Sig:', sig);
				var md5_sig = crypto.createHash('md5').update(sig).digest('hex');
				console.log('MD5 Sig:', md5_sig);
				data = querystring.stringify({
					app_id: process.env.MR_CLIENT_ID,
					method: 'users.getInfo',
					sig: md5_sig,
					session_key: mr_res.access_token,
					secure: '1'
				});
				options = {
					host: 'www.appsmail.ru',
					port: 80,
					path: '/platform/api?' + data,
					method: 'GET'
				};
				var httpreq = http.request(options, function(res) {
					if (res.statusCode != 200){
						res.on('data', function(chunk) {
							process.stdout.write(chunk);
						});
						callback('error');
					} else {
						var _json = '';
						res.setEncoding('utf8');
						res.on('data', function(chunk) {
							_json += chunk;
						});
						res.on('end', function() {
							console.log(_json);
							mr_res2 = JSON.parse(_json);
							console.log(mr_res2[0].email);
							callback(arg);
						});
					}
				});
				httpreq.end();
			} else if (arg == 'pgconnect') {
				var client = new pg.Client(dbconfig);
				client.connect(function(err) {
					if (err) {
						return console.error('Ошибка подключения к БД',err);
						callback('error');
					}
					client.query('select * from users where mr = $1', [mr_res2[0].uid], function(err, result) {
						if (err) {
							return console.error('Ошибка получения данных',err);
							callback('error');
						} else {
							if (result.rows[0]) {
								console.log(result.rows[0]);
								req.session.authorized = true;
								req.session.userid = result.rows[0].id;
								req.session.user_email = result.rows[0].email;
								req.session.mr = result.rows[0].mr;
								client.end();
								callback(arg);
							} else {
								console.log('Попытка создания нового пользователя. ');
								mr_res2.email = mr_res2.email || 'you@email.com';
								client.query("insert into users (email, mr) values ('" + mr_res2[0].email + "', " + mr_res2[0].uid + ") returning id", function(err, result) {
									if (err) {
										return console.error('Ошибка записи данных в БД', err);
										callback('error');
									} else {
										req.session.authorized = true;
										req.session.userid = result.rows[0].id;
										req.session.user_email = mr_res2[0].email;
										req.session.mr = mr_res2[0].uid;
										console.log('Добавлен новый пользователь # ' + result.rows[0].id);
									}
									client.end();
									callback(arg);
								});
							}
						}
					});
				});
			} else {
				callback('error');
			}
		}, 4);
	}

	function final() {
		console.log('Готовчик!'.yellow, results);
		if (req.session.authorized && results.indexOf('error') < 0) {
			res.redirect('http://' + req.headers.host + '/cabinet');
		} else {
			res.redirect('http://' + req.headers.host);
		}
	}

	var mr_res;
	var mr_res2;
	var items = ["httpsreq","get_user_data","pgconnect"];
	// var items = ["httpsreq","get_user_data","error"];
	var results = [];

	function series(item) {
		if (item) {
			async(item, function(result) {
				results.push(result);
				if (result == 'error')
					return final();
				else 
					return series(items.shift());
			});
		} else {
			return final();
		}
	}

	series(items.shift());
});

app.get('/yalogin', function(req, res) {
	console.log('Авторизация через сервис Яндекс'.green);

	var url_parts = url.parse(req.url, true);
	var query = url_parts.query;
	var data = querystring.stringify({
		grant_type: 'authorization_code',
		code: query.code,
		client_id: process.env.YA_CLIENT_ID,
		client_secret: process.env.YA_CLIENT_SECRET
	});
	var options = {
		host: 'oauth.yandex.ru',
		port: 443,
		path: '/token',
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Content-Length': data.length
		}
	};

	function async(arg, callback) {
		setTimeout(function() {
			console.log('Выполенение комманды ' + arg + '...');
			if (arg == 'httpsreq') {
				var httpsreq = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						res.on('data', function(chunk) {
							console.log('Yandex Error Response:', chunk);
						});
						callback('error');
					} else {
						var _json = '';
						res.on('data', function(chunk) {
							_json += chunk.toString();
						});
						res.on('end', function() {
							ya_res = JSON.parse(_json);
							callback(arg);
						});
					}
				});
				httpsreq.write(data);
				httpsreq.end();
			} else if (arg == 'get_user_data') {
				options = {
					host: 'login.yandex.ru',
					port: 443,
					path: '/info?' + querystring.stringify({ oauth_token: ya_res.access_token }),
					method: 'GET'
				};
				var httpsreq2 = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						res.on('data', function(chunk) {
							console.log(chunk);
						});
						callback('error');
					} else {
						var _json = '';
						res.on('data', function(chunk) {
							_json += chunk.toString();
						});
						res.on('end', function() {
							ya_res2 = JSON.parse(_json);
						});
						callback(arg);
					}
				});
				httpsreq2.end();
			} else if (arg == 'pgconnect') {
				var client = new pg.Client(dbconfig);
				client.connect(function(err) {
					if (err) {
						return console.error('Ошибка подключения к БД',err);
					}
					client.query("select * from users where ya = $1", [ya_res2.id], function(err, result) {
						if (err) {
							return console.error('Ошибка получения данных',err);
						} else {
							if (result.rows[0]) {
								console.log(result.rows[0]);
								req.session.authorized = true;
								req.session.userid = result.rows[0].id;
								req.session.user_email = result.rows[0].email;
								req.session.ya = result.rows[0].ya;
								client.end();
								callback(arg);
							} else {
								console.log('Попытка создания нового пользователя. ');
								ya_res2.default_email = ya_res2.default_email || 'vasia@ya.com';
								client.query("insert into users (email, ya) values ('" + ya_res2.default_email + "', " + ya_res2.id + ") returning id", function(err, result) {
									if (err) {
										return console.error('Ошибка записи данных в БД', err);
									} else {
										console.log('Добавлен новый пользователь # ' + result.rows[0].id);
										req.session.authorized = true;
										req.session.userid = result.rows[0].id;
										req.session.user_email = ya_res2.default_email;
										req.session.ya = ya_res2.id;
										client.end();
										callback(arg);
									}
								});
							}
						}
					});
				});
			}
		}, 4);
	}

	function final() {
		console.log('Готовчик!'.yellow, results);
		if (req.session.authorized && results.indexOf('error') < 0) {
			res.redirect('http://' + req.headers.host + '/cabinet');
		} else {
			res.redirect('http://' + req.headers.host);
		}
	}

	var ya_res;
	var ya_res2;
	var items = ["httpsreq","get_user_data","pgconnect"];
	var results = [];

	function series(item) {
		if (item) {
			async(item, function(result) {
				results.push(result);
				if (result == 'error')
					return final();
				else 
					return series(items.shift());
			});
		} else {
			return final();
		}
	}

	series(items.shift());
});

app.get('/bxlogin', function(req, res) {
	console.log('Авторизация через сервис Битрикс24'.green);

	var url_parts = url.parse(req.url, true);
	var query = url_parts.query;
	var data = querystring.stringify({
		client_id: process.env.BX_CLIENT_ID,
		grant_type: 'authorization_code',
		client_secret: process.env.BX_CLIENT_SECRET,
		redirect_uri: 'http://' + req.headers.host + '/bxlogin',
		code: query.code,
		scope: 'user'
	});
	var options = {
		host: 'lds.bitrix24.ru',
		port: 443,
		path: '/oauth/token/?' + data,
		method: 'GET'
	};

	function async(arg, callback) {
		setTimeout(function() {
			console.log('Выполенение комманды ' + arg + '...');
			if (arg == 'httpsreq') {
				var httpsreq = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						res.on('data', function(chunk) {
							console.log('Bitrix24 Error Response:', chunk);
						});
						callback('error');
					} else {
						var _json = '';
						res.on('data', function(chunk) {
							_json += chunk.toString();
						});
						res.on('end', function() {
							console.log(bx_res);
							bx_res = JSON.parse(_json);
							callback(arg);
						});
					}
				});
				httpsreq.write(data);
				httpsreq.end();
			} else if (arg == 'get_user_data') {
				options = {
					host: 'login.yandex.ru',
					port: 443,
					path: '/info?' + querystring.stringify({ oauth_token: bx_res.access_token }),
					method: 'GET'
				};
				var httpsreq2 = https.request(options, function(res) {
					res.setEncoding('utf8');
					if (res.statusCode != 200) {
						res.on('data', function(chunk) {
							console.log(chunk);
						});
						callback('error');
					} else {
						var _json = '';
						res.on('data', function(chunk) {
							_json += chunk.toString();
						});
						res.on('end', function() {
							bx_res2 = JSON.parse(_json);
						});
						callback(arg);
					}
				});
				httpsreq2.end();
			} else if (arg == 'pgconnect') {
				var client = new pg.Client(dbconfig);
				client.connect(function(err) {
					if (err) {
						return console.error('Ошибка подключения к БД',err);
					}
					client.query("select * from users where bx = $1", [bx_res2.id], function(err, result) {
						if (err) {
							return console.error('Ошибка получения данных',err);
						} else {
							if (result.rows[0]) {
								console.log(result.rows[0]);
								req.session.authorized = true;
								req.session.userid = result.rows[0].id;
								req.session.user_email = result.rows[0].email;
								req.session.bx = result.rows[0].bx;
								client.end();
								callback(arg);
							} else {
								console.log('Попытка создания нового пользователя. ');
								bx_res2.default_email = bx_res2.default_email || 'vasia@ya.com';
								client.query("insert into users (email, ya) values ('" + bx_res2.default_email + "', " + bx_res2.id + ") returning id", function(err, result) {
									if (err) {
										return console.error('Ошибка записи данных в БД', err);
									} else {
										console.log('Добавлен новый пользователь # ' + result.rows[0].id);
										req.session.authorized = true;
										req.session.userid = result.rows[0].id;
										req.session.user_email = bx_res2.default_email;
										req.session.ya = bx_res2.id;
										client.end();
										callback(arg);
									}
								});
							}
						}
					});
				});
			}
		}, 4);
	}

	function final() {
		console.log('Готовчик!'.yellow, results);
		if (req.session.authorized && results.indexOf('error') < 0) {
			res.redirect('http://' + req.headers.host + '/cabinet');
		} else {
			res.redirect('http://' + req.headers.host);
		}
	}

	var bx_res;
	var bx_res2;
	// var items = ["httpsreq","get_user_data","pgconnect"];
	var items = ["httpsreq"];
	var results = [];

	function series(item) {
		if (item) {
			async(item, function(result) {
				results.push(result);
				if (result == 'error')
					return final();
				else 
					return series(items.shift());
			});
		} else {
			return final();
		}
	}

	series(items.shift());
});

app.get('/logout', function(req, res, next) {
	console.log('Выход из личного кабинета'.red);
	req.session.destroy(function(err) {
		if (err) {
			console.log('Ошибка удаления сессии', err);
			next();
		} else {
			res.redirect('http://' + req.headers.host);
		}
	});
});

app.get('/cabinet', function(req, res) {
	console.log('Вход в личный кабинет'.green);

	function show_cabinet() {
		res.render('cabinet.jade', {
			title: 'Личный кабинет',
			mainpage_url: 'http://' + req.headers.host,
			cabinet_url: 'http://' + req.headers.host + '/cabinet',
			user_email: req.session.user_email,
			fb_id: req.session.fb,
			vk_id: req.session.vk,
			ok_id: req.session.ok,
			gp_id: req.session.gp,
			mr_id: req.session.mr,
			ya_id: req.session.ya
		});
	}

	function get_user_email(userid) {
		pg.connect(dbconfig, function(err, client, done) {
			if (err) {
				return console.error('Ошибка подключения к БД',err);
			}
			client.query('select * from users where vk = $1', [userid], function(err, result) {
				done();
				if (err) {
					console.error('Ошибка получения данных',err);
				} else {
					if (result.rows[0]) {
						console.log('Email пользователя: ' + result.rows[0].email);
						req.session.user_email = result.rows[0].email;
					}
					client.end();
				}
			});
		});
	}

	if (req.session.authorized) {
		show_cabinet();
	} else {
		res.redirect('http://' + req.headers.host);
	}
});

app.get('/db', function (req, res) {
  pg.connect(dbconfig, function(err, client, done) {
    if (err) {
      return console.error('Error fetching client from pool', err);
    }
    client.query('SELECT * FROM users', function(err, result) {
      done();
      if (err)
       { console.error(err); res.send("Error running query " + err); }
      else
       { res.send(result.rows); }
      client.end();
    });
  });
});

app.listen(app.get('port'), function() {
  console.log("Node app is running at localhost:" + app.get('port'));
});
