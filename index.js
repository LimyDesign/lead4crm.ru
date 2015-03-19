var express = require('express');
var h5bp = require('h5bp');
var pg = require('pg');
var app = express();

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
app.use(h5bp({ root: __dirname + '/public' }));
app.use(express.compress());
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

app.listen(app.get('port'), function() {
  console.log("Node app is running at localhost:" + app.get('port'));
});
