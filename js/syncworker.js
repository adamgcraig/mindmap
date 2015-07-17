//---Use EventSource if available, or fall back to Interval polling if not.---
//EventSource use based on http://www.htmlgoodies.com/beyond/reference/receive-updates-from-the-server-using-the-eventsource.html

//receiveupdate.php takes in the _REQUEST three variables,
//view: the file name of the view of interest,
//update_count: the number of updates the client has received so far, and
//updates: a JSON representation of an array of update objects the client has generated locally.
//It echoes a complete list of all the updates the client has not received yet, including the ones it just sent.
//var viewId = null;--defined in view.js
var editCount = 0;
var updateInterval = null;//for long-polling for updates
var updateSource = null;//for server-generated update events

//---Handle received updates the same way whether using EventSource or Interval polling.---

function parseUpdates(updatesJSON) {
    //console.log("received response: "+updatesJSON);
    try {
        var updates = JSON.parse(updatesJSON);
    }
    catch(exception) {
        console.log("could not parse response text: ");
        console.log(updatesJSON);
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
    //We sometimes get the same edits re-sent. To guard against this, check the ID.
    for(var editIndex = 0; editIndex < updates.length; editIndex++) {
        if(updates[editIndex]['historyIndex'] > editCount) {
            //postMessage( updates[editIndex] );
            handleUpdateFromServer( updates[editIndex] );//--defined in view.js
            editCount = updates[editIndex]['historyIndex'];
        }
    }//end of loop through edits
}//end of function parseUpdates

//Even though receiveupdate.php both receives updates and sends them out, do these things in separate calls.
//We currently send an update when we get it from the main (user interface) thread.
//We receive an update either when we poll the server or when we receive a server-generated event.
//At present, the client does not have a way to determine when it is receiving the same update twice,
//so send updates using sendUpdateAJAX, disregarding the updates it returns,
//and, when polling the server, use requestUpdateAJAX, not sending any updates.
function requestUpdateAJAX() {
    var updateRequest = new XMLHttpRequest();
    updateRequest.onload = function() {
        //console.log("receieved AJAX response:");
        //console.log(this.responseText);
        parseUpdates(this.responseText);
    };// end of onload
    var url = "../php/receiveupdate.php?view=" + encodeURIComponent(viewId)
                            + "&update_count=" + encodeURIComponent(editCount);
    updateRequest.open("get",url,true);
    updateRequest.send();
    console.log("sent request: "+url);
}// end of function requestUpdate

function onUpdateEventSource(evt) {
    console.log("received server-generated event");
    console.log(evt);
    parseUpdates(evt.data);
}// end of function onUpdate

function onErrorEventSource(err) {
    console.log("event source encountered an error:");
    console.log(err);
}// end of function onErrorEventSource

function setUpdateRetrievalMode() {
    //Close any pre-existing event source before starting a new one.
    if(updateSource !== null) {
        updateSource.close();
        updateSource = null;
    }//end of if we have an old view for which to tear down updates
    //We should have at most one interval going at a time.
    if(updateInterval !== null) {
        clearInterval(updateInterval);
        updateInterval = null;
    }//end of if we have an old view for which to tear down updates
    //I do not entirely trust if( typeof EventSource === 'undefined').
    //It evaluates to true on Firefox even though server generated events are supported since version 11.
    if(viewId != null) {
        try {
            //Use server-generated events if possible.
            console.log("Setting up event source:");
            updateSource = new EventSource("../php/sendmessageonupdate.php?view=" + encodeURIComponent(viewId)
                                                               + "&update_count=" + encodeURIComponent(editCount) );
            updateSource.onmessage = onUpdateEventSource;
            updateSource.onerror = onErrorEventSource;
            updateSource.addEventListener("ping", function(e) {
                                                      console.log("new server-generated event:");
                                                      console.log(e);
                                                   }, false);
            console.log(updateSource);
        }//end of if EventSource supported
        catch(e) {
            //Fall back to using interval.
            console.log("EventSource not supported, falling back to polling server at intervals.");
            updateInterval = setInterval(requestUpdateAJAX, 1000);//Request updates once per second.
        }//end of if EventSource not supported
    }//end of if we have a new view for which to set up updates
}// end of function setUpdateBehavior

function sendUpdateAJAX(singleUpdate) {
    var updateRequest = new XMLHttpRequest();
    var updateString = JSON.stringify({ 'updates': [ singleUpdate ] });
    //Use onload as a check that the update it sends back is the one we sent to it.
    updateRequest.onload = function() {
        console.log("request sent: "+updateString);
        console.log("and received: "+this.responseText);
        console.log( "same: "+(updateString == this.responseText) );
    };
    var url = "../php/receiveupdate.php?view=" + encodeURIComponent(viewId)
                            + "&update_count=" + encodeURIComponent(editCount)
                                 + "&updates=" + encodeURIComponent(updateString);
    updateRequest.open("get",url,true);
    updateRequest.send();
    console.log("sent request: "+url);
}// end of function requestUpdate

//--called in view.js
function sendEditToServer(edit) {
    console.log("sendEditToServer: new edit: ");
    console.log(edit);
    if(edit['edit'] == 'setViewId') {
        console.log("received new view ID: "+edit['viewId']);
        //viewId = edit['viewId'];
        setUpdateRetrievalMode();
    }//end of if we have a view ID
    else {
        //When we get an update, push it to the server.
        sendUpdateAJAX(edit);
    }//end of if we are adding data to send to the server
};//end of window.onmessage

//onmessage = function(event) {
//    handleEdit(event.data);
//};//end of window.onmessage