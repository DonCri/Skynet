function handleMessage(message) {

  var data = JSON.parse(message);
  console.log(data);

  var date = new Date(data.Timestamp * 1000);
  var formattedTimestamp = date.toLocaleString('de-CH');

  switch (data.Ident) {
    case 'SKYNET_STATE':
      if (data.Value == true) {
        document.getElementById("logo").src = window.assets.logo_green;
      } else {
        document.getElementById("logo").src = window.assets.logo_red;
      }
      break;

    case 'MESSAGE':
      document.getElementById('messageName').textContent = data.Name;
      document.getElementById('aiMessage').textContent = data.Value;
      document.getElementById('messageTimestamp').textContent = formattedTimestamp;
      break;

    default:
      break;
  }
}
