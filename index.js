var express = require('express');
var session = require('express-session');
var connect = require('connect');
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

app.set('port', (process.env.PORT || 5000));
app.set('/views', __dirname + '/views');
app.use(express.static(__dirname + '/public'));
app.use(connect.cookieParser());

app.use(session({
	key: 'lead4crm',
	secret: "kksdjhfjgjhwehrfgvbksdhfgjfytrweyur",
	resave: false,
	saveUninitialized: true
}));

app.get('/', function(req, res) {
	var vklogin_query = querystring.stringify({
		client_id: '4836170',
		scope: 'notify,email',
		redirect_uri: 'http://' + req.headers.host + '/vklogin',
		response_type: 'code',
		v: '5.29',
		state: crypto.createHmac('sha1', req.headers['user-agent'] + new Date().getTime()).digest('hex'),
		display: 'page'
	});
	res.render('index.jade', {
		title: 'Генератор лидов для Битрикс24',
		vklogin: 'https://oauth.vk.com/authorize?' + vklogin_query
	});
});

app.get('/vklogin', function(req, res) {
	console.log('Авторизация через соц.сеть "Вконтакте"'.green);

	var url_parts = url.parse(req.url, true);
	var query = url_parts.query;
	var data = querystring.stringify({
		client_id: '4836170',
		client_secret: 'cPkR53zhon0lU7TAiz9f',
		code: query.code,
		redirect_uri: 'http://' + req.headers.host + '/vklogin'
	});
	var options = {
		host: 'oauth.vk.com',
		port: 443,
		path: '/access_token?' + data,
		method: 'GET'
	};
	var httpsreq = https.request(options, function(res) {
		res.setEncoding('utf8');
		res.on('data', function(chunk) {
			var chunk = JSON.parse(chunk);
			pg.connect(dbconfig, function(err, client, done) {
				if (err) {
					return console.error('Ошибка подключения к БД',err);
				}
				client.query('select * from users where vk = $1', [chunk.user_id], function(err, result) {
					done();
					if (err) {
						console.error('Ошибка получения данных',err);
					} else {
						if (result.rows[0]) {
							console.log(result.rows[0]);
							req.session.authorized = true;
							req.session.userid = result.rows[0].id;
						} else {
							console.log('Попытка создания нового пользователя. ');
							client.query("insert into users (email, vk) values ('" + chunk.email + "', " + chunk.user_id + ") returning id", function(err, result) {
								done();
								if (err) {
									console.error('Ошибка записи данных в БД', err);
								} else {
									req.session.authorized = true;
									req.session.userid = result.rows[0].id;
									console.log('Добавлен новый пользователь # ' + result.rows[0].id);
								}
							});
						}

					}
					client.end();
				});
			});
		});
	});
	// setTimeout(function() {
	// 	res.writeHead(301, {
	// 		Location: 'http://' + req.headers.host
	// 	});
	// 	res.end();
	// }, 10);
	var vklogin_query = querystring.stringify({
		client_id: '4836170',
		scope: 'notify,email',
		redirect_uri: 'http://' + req.headers.host + '/vklogin',
		response_type: 'code',
		v: '5.29',
		state: crypto.createHmac('sha1', req.headers['user-agent'] + new Date().getTime()).digest('hex'),
		display: 'page'
	});
	res.render('index.jade', {
		title: 'Генератор лидов для Битрикс24',
		vklogin: 'https://oauth.vk.com/authorize?' + vklogin_query
	});
});

app.get('/oklogin', function(req, res) {
	// res.send(req.headers);
	var shasum = crypto.createHmac('sha1', req.headers['user-agent'] + new Date().getTime());
	var d = shasum.digest('hex');
	res.send(crypto.createHmac('sha1', req.headers['user-agent'] + new Date().getTime()).digest('hex'));
});

app.get('/cabinet', function(req, res) {
	console.log('Вход в личный кабинет'.green);
	if (req.session.authorized) {
		res.send('Ваш ID: ' + req.session.userid);
	} else {
		res.send('Вы не авторизованны!');
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
