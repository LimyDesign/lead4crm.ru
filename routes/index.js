app.get('/', function(request, response) {
	response.render('index', {title: 'Lead4CRM'});
});

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