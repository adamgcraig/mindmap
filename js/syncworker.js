var view = null;
var editQueue = [];
var editCount = 0;

//syncupdates.php takes in the _REQUEST three variables,
//view: the file name of the view of interest,
//update_count: the number of updates the client has received so far, and
//updates: a JSON representation of an array of update objects the client has generated locally.
//It echoes a complete list of all the updates the client has not received yet, including the ones it just sent.

onmessage = function(event) {
    //console.log("received message from main thread: ");
    //console.log(event.data);
    if(event.data['edit'] == 'setViewId') {
        console.log("received new view ID: "+event.data['viewId']);
        view = event.data['viewId'];
        return;
    }
    editQueue.push(event.data);
}// end of onmessage from main view thread

function onUpdate() {
    //console.log("received response: "+this.responseText);
    try {
        var updates = JSON.parse(this.responseText);
    }
    catch(exception) {
        console.log("could not parse response text: ");
        console.log(this.responseText);
        console.log(exception);
        return;
    }
    if(updates.hasOwnProperty('error')) {
        console.log("error from server:");
        console.log(updates);
        return;
    }
    //else {
    //    console.log("received updates:");
    //    console.log(updates);
    //}
    //For some reason, the JSON parsers expect the numeric arrays to come wrapped in Objects.
    updates = updates['updates'];
    for(var editIndex = 0; editIndex < updates.length; editIndex++) {
        postMessage( updates[editIndex] );
    }//end of loop through edits
    editCount = editCount + updates.length;
}// end of function onUpdate

function requestUpdate() {
    if(view == null) {
        console.log("view is still null");
        return;
    }
    var updateRequest = new XMLHttpRequest();
    updateRequest.onload = onUpdate;
    var url = "../php/syncupdates.php?view=" + encodeURIComponent(view)
                          + "&update_count=" + encodeURIComponent(editCount) 
                               + "&updates=" + encodeURIComponent( JSON.stringify({ 'updates': editQueue }) );
    editQueue = [];//Empty the edit queue now that we have dumped all the edits into the request for transmission to the server.
    updateRequest.open("get",url,true);
    updateRequest.send();
    //console.log("sent request: "+url);
}// end of function requestUpdate

var updateInterval = setInterval(requestUpdate, 500);//milliseconds 1frame/50milliseconds = 1000frames/50seconds = 20 frames/second