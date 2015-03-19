var express = require('express');
var app = express();
var cool = require('cool-ascii-faces');
var pg = require('pg');

var host = 'ec2-54-247-125-202.eu-west-1.compute.amazonaws.com';
var database = 'dcuaceirnqfh7a';
var user = 'fukhhuzwpyiphq';
var port = '5432';

var config = {
	host: host,
	port: port,
	database: database,
	user: user,
	password: 'SYSw21dqJoIy6c8uVoKMzQvTu1',
	ssl: true
};

app.set('port', (process.env.PORT || 5000));
app.use(express.static(__dirname + '/public'));

app.get('/db', function (request, response) {
  pg.connect(config, function(err, client, done) {
  	if (err) {
  		return console.error('Error fetching client from pool', err);
  	}
    client.query('SELECT * FROM test_table', function(err, result) {
      done();
      if (err)
       { console.error(err); response.send("Error running query " + err); }
      else
       { response.send(result.rows); }
   	  client.end();
    });
  });
});

app.get('/cool', function(request, response) {
	response.send(cool());
});

app.get('/', function(request, response) {
	var result = '';
	var times = process.env.TIMES || 5;
	for (i=0; i < times; i++)
		result += cool() + "<br>";
	response.send(result);
});

app.listen(app.get('port'), function() {
  console.log("Node app is running at localhost:" + app.get('port'));
});
