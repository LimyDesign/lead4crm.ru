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
		// scope: 'GET_EMAIL',
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
	})
	res.render('index.jade', {
		title: 'Генератор лидов для Битрикс24',
		vklogin: 'https://oauth.vk.com/authorize?' + vklogin_query,
		oklogin: 'http://www.odnoklassniki.ru/oauth/authorize?' + oklogin_query,
		fblogin: 'https://www.facebook.com/dialog/oauth?' + fblogin_quey,
		mainpage_url: 'http://' + req.headers.host,
		cabinet: cabinet,
		cabinet_url: 'http://' + req.headers.host + '/cabinet'
	});
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
										req.session.user_email = result.rows[0].email;
										req.session.vk = result.rows[0].vk;
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
										req.session.user_email = result.rows[0].email;
										req.session.ok = result.rows[0].ok;
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
			vk_id: req.session.vk,
			ok_id: req.session.ok
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
