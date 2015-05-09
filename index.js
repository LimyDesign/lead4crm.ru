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

// Настройка подключения к БД
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

// Общие параметры: по-умолчанию кабинет закрыт,
// указываются URI для ссылок в меню
var cabinet = false;
var cabinet_uri = '/cabinet';
var about_project_uri = '/about-project';
var about_us_uri = '/about-us';
var price_uri = '/price';
var support_uri = '/support';

// Общие настройки сайта
app.set('port', (process.env.PORT || 5000));
app.set('/views', __dirname + '/views');
app.use(compress());
app.use(express.static(__dirname + '/public'));
app.use(connect.cookieParser());

// Создаем куку для нашей сессии
app.use(session({
	key: 'lead4crm',
	secret: crypto.createHash('md5').digest('hex'),
	resave: false,
	saveUninitialized: true
}));

// Конструктор запросов для oAuth провайдеров
function login_query(provider, req) {
	if (provider == 'vkontakte') {
		return querystring.stringify({
			client_id: process.env.VK_CLIENT_ID,
			scope: 'email',
			redirect_uri: 'http://' + req.headers.host + '/vklogin',
			response_type: 'code',
			v: '5.29',
			state: req.session.oauth_state,
			display: 'page'
		});
	} else if (provider == 'odnoklassniki') {
		return querystring.stringify({
			client_id: process.env.OK_CLIENT_ID,
			scope: 'GET_EMAIL',
			response_type: 'code',
			redirect_uri: 'http://' + req.headers.host + '/oklogin',
			// layout: 'w',
			state: req.session.oauth_state
		});
	} else if (provider == 'facebook') {
		return querystring.stringify({
			client_id: process.env.FB_CLIENT_ID,
			scope: 'email',
			redirect_uri: 'http://' + req.headers.host + '/fblogin',
			response_type: 'code'
		});
	} else if (provider == 'google-plus') {
		return querystring.stringify({
			client_id: process.env.GP_CLIENT_ID,
			scope: 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
			redirect_uri: 'http://' + req.headers.host + '/gplogin',
			response_type: 'code',
			state: req.session.oauth_state,
			access_type: 'online',
			approval_prompt: 'auto',
			login_hint: 'email',
			include_granted_scopes: 'true'
		});
	} else if (provider == 'mailru') {
		return querystring.stringify({
			client_id: process.env.MR_CLIENT_ID,
			response_type: 'code',
			redirect_uri: 'http://' + req.headers.host + '/mrlogin'
		});
	} else if (provider == 'yandex') {
		return querystring.stringify({
			response_type: 'code',
			client_id: process.env.YA_CLIENT_ID,
			state: req.session.oauth_state
		});
	}
}

// Проверка авторизации
function is_auth(req) {
	if (req.session.authorized)
		return true;
	else
		return false;
}

// 2-й конструктор запросов к oAuth провайдерам
function oAuthData(client_data, req, prov) {
	var query = url.parse(req.url, true).query;
	if (client_data.grant_type) {
		return querystring.stringify({
			client_id: client_data.client_id,
			client_secret: client_data.client_secret,
			code: query.code,
			redirect_uri: 'http://' + req.headers.host + '/login/' + prov,
			grant_type: client_data.grant_type
		});
	} else {
		return querystring.stringify({
			client_id: client_data.client_id,
			client_secret: client_data.client_secret,
			code: query.code,
			redirect_uri: 'http://' + req.headers.host + '/login/' + prov
		});
	}
}

// конструктор опций запроса
function oAuthOptions(opt) {
	if (opt.method == 'GET') {
		return {
			host: opt.host,
			port: opt.port,
			path: opt.path + '?' + opt.data,
			method: opt.method
		};
	} else {
		return {
			host: opt.host,
			port: opt.port,
			path: opt.path,
			method: opt.method,
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'Content-Length': opt.data.length
			}
		};
	}
}

// функция пошаговой авторизации:
// 1. Получение токена
// 2. Получение данных пользователя
// 3. Запись данных в базу данных
function oAuthAsync(cmd, callback) {
	setTimeout(function() {
		console.log('Выполенение комманды ' + cmd + '...');
		switch(cmd) {
			case 'getToken':
			case 'getUserData':
			case 'rwDB':
			default:
		}
	}, 4);
}

// Функция перенаправления после авторизации:
// в случае удачи переадресует в кабинет,
// в случае провала кидает на главную страницу
function oAuthFinal(req, results) {
	console.log('Готовчик!'.yellow, res);
	if (req.session.authorized && results.indexOf('error') < 0) {
		res.redirect('http://' + req.headers.host + '/cabinet');
	} else {
		res.redirect('http://' + req.headers.host);
	}
}

// функция хуй знает чего, позже разберусь
// хуёво когда нет комментариев
function oAuthSerial(cmd, req) {
	if (cmd) {
		oAuthAsync(cmd, function(results) {
			results.push(results);
			if (results == 'error')
				return oAuthFinal(req, results);
			else 
				return oAuthSeries(cmd.shift(), req);
		});
	} else {
		return oAuthFinal(req, results);
	}
}

// функция получиения списка тарифов 
// для конкретного пользователя 
// в зависимости от текущего баланса
function getTariffList(req) {

}

// Главная страница
app.get('/', function(req, res) {
	var oauth_state = crypto.createHmac('sha1', req.headers['user-agent'] + new Date().getTime()).digest('hex');
	req.session.oauth_state = req.session.oauth_state || oauth_state;
	
	res.render('index.jade', {
		title: 'Генератор лидов для Битрикс24',
		vklogin: 'https://oauth.vk.com/authorize?' + login_query('vkontakte', req),
		oklogin: 'http://www.odnoklassniki.ru/oauth/authorize?' + login_query('odnoklassniki', req),
		fblogin: 'https://www.facebook.com/dialog/oauth?' + login_query('facebook', req),
		gplogin: 'https://accounts.google.com/o/oauth2/auth?' + login_query('google-plus', req),
		mrlogin: 'https://connect.mail.ru/oauth/authorize?' + login_query('mailru', req),
		yalogin: 'https://oauth.yandex.ru/authorize?' + login_query('yandex', req),
		mainpage_url: 'http://' + req.headers.host,
		aboutproject_url: 'http://' + req.headers.host + about_project_uri,
		aboutours_url: 'http://' + req.headers.host + about_us_uri,
		prices_url: 'http://' + req.headers.host + price_uri,
		support_url: 'http://' + req.headers.host + support_uri,
		cabinet_url: 'http://' + req.headers.host + cabinet_uri,
		cabinet: is_auth(req),
		currentUrl: 'http://' + req.headers.host
	});
});

// Операции с личным кабинетом
app.get('/cabinet/:command/:plan', function(req, res, next) {
	var plan = req.params.plan;
	var command = req.params.command;
	if (command) {
		if (plan) {
			console.log(plan);
			res.redirect('http://' + req.headers.host + cabinet_uri);
		} else {
			next();
		}
	} else {
		next();
	}
});

app.get('/cabinet/:command', function(req, res, next) {
	next();
});

// Персональный кабинет
app.get('/cabinet', function(req, res, next) {
	console.log('Вход в личный кабинет'.green);

	function show_cabinet() {
		res.render('cabinet.jade', {
			title: 'Личный кабинет',
			mainpage_url: 'http://' + req.headers.host,
			cabinet_url: 'http://' + req.headers.host + cabinet_uri,
			user_email: req.session.user_email,
			fb_id: req.session.fb,
			vk_id: req.session.vk,
			ok_id: req.session.ok,
			gp_id: req.session.gp,
			mr_id: req.session.mr,
			ya_id: req.session.ya,
			apikey: req.session.apikey,
			tariff_select: getTariffList()
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

	function getTariffList() {
		pg.connect(dbconfig, function(err, client, done) {
			if (err) {
				return console.error('Ошибка подключения к БД',err);
			}
			client.query('select * from tariff where domain = $1', ['lead4crm.ru'], function(err, result) {
				done();
				if (err) {
					console.error('Ошибка получения данных',err);
				} else {
					var tariffs = [];
					for (var i = 0; i < result.rows.length; i++) {
						var row = result.rows[i];
						var tCode = row.code;
						var tName = row.name;
						tariffs.push({tCode: tName});
					}
					console.log(tariffs);
					return tariffs;
				}
			})
		})
	}

	if (req.session.authorized) {
		show_cabinet();
	} else {
		res.redirect('http://' + req.headers.host);
	}
});

// Страница про проект
app.get('/about-project', function(req, res) {
	var oauth_state = crypto.createHmac('sha1', req.headers['user-agent'] + new Date().getTime()).digest('hex');
	req.session.oauth_state = req.session.oauth_state || oauth_state;

	res.render('about-project.jade', {
		title: 'О проекте',
		vklogin: 'https://oauth.vk.com/authorize?' + login_query('vkontakte', req, req.session.oauth_state),
		oklogin: 'http://www.odnoklassniki.ru/oauth/authorize?' + login_query('odnoklassniki', req, req.session.oauth_state),
		fblogin: 'https://www.facebook.com/dialog/oauth?' + login_query('facebook', req, req.session.oauth_state),
		gplogin: 'https://accounts.google.com/o/oauth2/auth?' + login_query('google-plus', req, req.session.oauth_state),
		mrlogin: 'https://connect.mail.ru/oauth/authorize?' + login_query('mailru', req, req.session.oauth_state),
		yalogin: 'https://oauth.yandex.ru/authorize?' + login_query('yandex', req, req.session.oauth_state),
		mainpage_url: 'http://' + req.headers.host,
		aboutproject_url: 'http://' + req.headers.host + about_project_uri,
		aboutours_url: 'http://' + req.headers.host + about_us_uri,
		prices_url: 'http://' + req.headers.host + price_uri,
		support_url: 'http://' + req.headers.host + support_uri,
		cabinet_url: 'http://' + req.headers.host + cabinet_uri,
		cabinet: is_auth(req),
		currentUrl: 'http://' + req.headers.host + about_project_uri
	});
});

// Страница про нас
app.get('/about-us', function(req, res) {
	var oauth_state = crypto.createHmac('sha1', req.headers['user-agent'] + new Date().getTime()).digest('hex');
	req.session.oauth_state = req.session.oauth_state || oauth_state;

	res.render('about-us.jade', {
		title: 'О нас',
		vklogin: 'https://oauth.vk.com/authorize?' + login_query('vkontakte', req, req.session.oauth_state),
		oklogin: 'http://www.odnoklassniki.ru/oauth/authorize?' + login_query('odnoklassniki', req, req.session.oauth_state),
		fblogin: 'https://www.facebook.com/dialog/oauth?' + login_query('facebook', req, req.session.oauth_state),
		gplogin: 'https://accounts.google.com/o/oauth2/auth?' + login_query('google-plus', req, req.session.oauth_state),
		mrlogin: 'https://connect.mail.ru/oauth/authorize?' + login_query('mailru', req, req.session.oauth_state),
		yalogin: 'https://oauth.yandex.ru/authorize?' + login_query('yandex', req, req.session.oauth_state),
		mainpage_url: 'http://' + req.headers.host,
		aboutproject_url: 'http://' + req.headers.host + about_project_uri,
		aboutours_url: 'http://' + req.headers.host + about_us_uri,
		prices_url: 'http://' + req.headers.host + price_uri,
		support_url: 'http://' + req.headers.host + support_uri,
		cabinet_url: 'http://' + req.headers.host + cabinet_uri,
		cabinet: is_auth(req),
		currentUrl: 'http://' + req.headers.host + about_us_uri
	});
});

// Страница с ценами
app.get('/price', function(req, res) {
	var oauth_state = crypto.createHmac('sha1', req.headers['user-agent'] + new Date().getTime()).digest('hex');
	req.session.oauth_state = req.session.oauth_state || oauth_state;

	res.render('prices.jade', {
		title: 'Цены',
		vklogin: 'https://oauth.vk.com/authorize?' + login_query('vkontakte', req, req.session.oauth_state),
		oklogin: 'http://www.odnoklassniki.ru/oauth/authorize?' + login_query('odnoklassniki', req, req.session.oauth_state),
		fblogin: 'https://www.facebook.com/dialog/oauth?' + login_query('facebook', req, req.session.oauth_state),
		gplogin: 'https://accounts.google.com/o/oauth2/auth?' + login_query('google-plus', req, req.session.oauth_state),
		mrlogin: 'https://connect.mail.ru/oauth/authorize?' + login_query('mailru', req, req.session.oauth_state),
		yalogin: 'https://oauth.yandex.ru/authorize?' + login_query('yandex', req, req.session.oauth_state),
		mainpage_url: 'http://' + req.headers.host,
		aboutproject_url: 'http://' + req.headers.host + about_project_uri,
		aboutours_url: 'http://' + req.headers.host + about_us_uri,
		prices_url: 'http://' + req.headers.host + price_uri,
		support_url: 'http://' + req.headers.host + support_uri,
		cabinet_url: 'http://' + req.headers.host + cabinet_uri,
		cabinet: is_auth(req),
		currentUrl: 'http://' + req.headers.host + price_uri
	});
});

// Страница поддержки
app.get('/support', function(req, res) {
	var oauth_state = crypto.createHmac('sha1', req.headers['user-agent'] + new Date().getTime()).digest('hex');
	req.session.oauth_state = req.session.oauth_state || oauth_state;

	res.render('support.jade', {
		title: 'Поддержка',
		vklogin: 'https://oauth.vk.com/authorize?' + login_query('vkontakte', req, req.session.oauth_state),
		oklogin: 'http://www.odnoklassniki.ru/oauth/authorize?' + login_query('odnoklassniki', req, req.session.oauth_state),
		fblogin: 'https://www.facebook.com/dialog/oauth?' + login_query('facebook', req, req.session.oauth_state),
		gplogin: 'https://accounts.google.com/o/oauth2/auth?' + login_query('google-plus', req, req.session.oauth_state),
		mrlogin: 'https://connect.mail.ru/oauth/authorize?' + login_query('mailru', req, req.session.oauth_state),
		yalogin: 'https://oauth.yandex.ru/authorize?' + login_query('yandex', req, req.session.oauth_state),
		mainpage_url: 'http://' + req.headers.host,
		aboutproject_url: 'http://' + req.headers.host + about_project_uri,
		aboutours_url: 'http://' + req.headers.host + about_us_uri,
		prices_url: 'http://' + req.headers.host + price_uri,
		support_url: 'http://' + req.headers.host + support_uri,
		cabinet_url: 'http://' + req.headers.host + cabinet_uri,
		cabinet: is_auth(req),
		currentUrl: 'http://' + req.headers.host + support_uri
	});
});

// Авторизация пользователей
app.get('/login/:provider', function(req, res, next) {
	var provider = req.params.provider;
	if (provider) {
		var prov_res = [];
		var command = [];
		var results = [];

		switch(provider) {
			case 'facebook':
				console.log('Авторизация через соц.сеть «Facebook»...'.green);
				var data = oAuthData({
					client_id: process.env.FB_CLIENT_ID,
					client_secret: process.env.FB_CLIENT_SECRET
				}, req, provider);
				var options = oAuthOptions({
					host: 'graph.facebook.com',
					port: 443,
					path: '/oauth/access_token',
					data: data,
					method: 'GET'
				});
				command = ['getToken','getUserData','rwDB'];
				oAuthSeries(command.shift(), results);
				break;
			case 'vkontakte':
				console.log('Авторизация через соц.сеть «Вконтакте»...'.green);
				var data = oAuthData({
					client_id: process.env.VK_CLIENT_ID,
					client_secret: process.env.VK_CLIENT_SECRET
				}, req, provider);
				var options = oAuthOptions({
					host: 'oauth.vk.com',
					port: 443,
					path: '/access_token',
					data: data,
					method: 'GET'
				});
				command = ['getToken','rwDB'];
				oAuthSeries(command.shift(), results);
				break;
			case 'odnoklassniki':
				console.log('Авторизация через соц.сеть «Одноклассники»'.green);
				var data = oAuthData({
					client_id: process.env.OK_CLIENT_ID,
					client_secret: process.env.OK_SECRET_KEY,
					grant_type: 'authorization_code'
				}, req, provider);
				var options = oAuthOptions({
					host: 'api.odnoklassniki.ru',
					port: 443,
					path: '/oauth/token.do',
					data: data,
					method: 'POST'
				});
				command = ['getToken','getUserData','rwDB'];
				oAuthSeries(command.shift(), results);
				break;
			case 'google-plus':
				console.log('Авторизация через сервис Google'.green);
				var data = oAuthData({
					client_id: process.env.GP_CLIENT_ID,
					client_secret: process.env.GP_CLIENT_SECRET,
					grant_type: 'authorization_code'
				}, req, provider);
				var options = oAuthOptions({
					host: 'accounts.google.com',
					port: 443,
					path: '/o/oauth2/token',
					data: data,
					method: 'POST'
				});
				command = ['getToken','getUserData','rwDB'];
				oAuthSeries(command.shift(), results);
				break;
			case 'mailru':
				console.log('Авторизация через сервис Mail.Ru'.green);
				var data = oAuthData({
					client_id: process.env.MR_CLIENT_ID,
					client_secret: process.env.MR_SECRET_KEY,
					grant_type: 'authorization_code'
				}, req, provider);
				var options = oAuthOptions({
					host: 'connect.mail.ru',
					port: 443,
					path: '/oauth/token',
					data: data,
					method: 'POST'
				});
				command = ['getToken','getUserData','rwDB'];
				oAuthSeries(command.shift(), results);
				break;
			case 'yandex':
				console.log('Авторизация через сервис Яндекс'.green);
				var data = oAuthData({
					client_id: process.env.YA_CLIENT_ID,
					client_secret: process.env.YA_CLIENT_SECRET,
					grant_type: 'authorization_code'
				}, req, provider);
				var options = oAuthOptions({
					host: 'oauth.yandex.ru',
					port: 443,
					path: '/token',
					data: data,
					method: 'POST'
				});
				command = ['getToken','getUserData','rwDB'];
				oAuthSeries(command.shift(), results);
				break;
			default:
				res.send('Данный вид авторизации не поддерживается нашим сайтом.');
		}
	} else {
		res.redirect('http://' + req.headers.host);
	}
});

app.get('/login', function(req, res) {
	res.send('Необходимо указать тип авторизации');
});

// Авторизация пользователей через сервис Facebook
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
								req.session.vk = result.rows[0].vk;
								req.session.ok = result.rows[0].ok;
								req.session.fb = result.rows[0].fb;
								req.session.gp = result.rows[0].gp;
								req.session.mr = result.rows[0].mr;
								req.session.ya = result.rows[0].ya;
								req.session.apikey = result.rows[0].apikey;
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

// Авторизация пользователей через сервис Вконтакте
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
								req.session.ok = result.rows[0].ok;
								req.session.fb = result.rows[0].fb;
								req.session.gp = result.rows[0].gp;
								req.session.mr = result.rows[0].mr;
								req.session.ya = result.rows[0].ya;
								req.session.apikey = result.rows[0].apikey;
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

// Авторизация пользователей через сервис Одноклассники
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
								req.session.vk = result.rows[0].vk;
								req.session.ok = result.rows[0].ok;
								req.session.fb = result.rows[0].fb;
								req.session.gp = result.rows[0].gp;
								req.session.mr = result.rows[0].mr;
								req.session.ya = result.rows[0].ya;
								req.session.apikey = result.rows[0].apikey;
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

// Авторизация пользователей через сервис Google
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
								req.session.vk = result.rows[0].vk;
								req.session.ok = result.rows[0].ok;
								req.session.fb = result.rows[0].fb;
								req.session.gp = result.rows[0].gp;
								req.session.mr = result.rows[0].mr;
								req.session.ya = result.rows[0].ya;
								req.session.apikey = result.rows[0].apikey;
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

// Авторизация пользователей через сервис Mail.Ru
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
								req.session.vk = result.rows[0].vk;
								req.session.ok = result.rows[0].ok;
								req.session.fb = result.rows[0].fb;
								req.session.gp = result.rows[0].gp;
								req.session.mr = result.rows[0].mr;
								req.session.ya = result.rows[0].ya;
								req.session.apikey = result.rows[0].apikey;
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

// Авторизация пользователей через сервис Яндекс
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
								req.session.vk = result.rows[0].vk;
								req.session.ok = result.rows[0].ok;
								req.session.fb = result.rows[0].fb;
								req.session.gp = result.rows[0].gp;
								req.session.mr = result.rows[0].mr;
								req.session.ya = result.rows[0].ya;
								req.session.apikey = result.rows[0].apikey;
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

// Выход
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

// Запуск HTTP сервера
app.listen(app.get('port'), function() {
  console.log("Node app is running at localhost:" + app.get('port'));
});
