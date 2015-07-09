var DATE_PICKER_PREFIX = 'datePicker';
var noResultsRow = null;
//sources: {
//    viewName1: { 'use':true/false, 'cutoff':(int)unixtimestamp },
//    viewName2: { 'use':true/false, 'cutoff':(int)unixtimestamp },
//    viewName3: { 'use':true/false, 'cutoff':(int)unixtimestamp },
//    ...
//}
var sources = new Object();
var USE = 'use';
var CUTOFF = 'cutoff';
var hasSearchedOnce = false;//Require that the user search for existing views at least once before making new one.

//When the page loads, get a reference to the no-results item so that we can
//remove it when the search does yield results,
//use it as a template for the search result list items,
//and put it back when the search does not yield results.
function init() {
    noResultsRow = document.getElementById('noResultsRow');
}//end of function init

function timestampToDatetimeLocalString(timestampInSeconds) {
    //PHP gives us timestamps in seconds, but JS assumes timestamps in milliseconds.
    //The datetime-local input type expects values in the format YYY-MM-DDThh:mm:ss.ms.
    //The ISO string is this plus a few extra digits of fractional seconds and a 'Z' to indicate UTC time. 
    var string = ( new Date(1000*timestampInSeconds) ).toISOString();
    string = string.substring(0,string.length-1);//Remove the 'Z' at the end.
    console.log("converted timestamp "+timestampInSeconds+" to datetime string "+string);
    return string;
}//end of function extractLocalISODatetime

function datetimeLocalStringToTimestamp(string) {
    var timestamp = Date.parse(string+'Z');//Append Z to tell the parser that this string is a UTC datetime.
    if( isNaN(timestamp) ) {
        console.log("could not create timestamp from date string "+string+", using current time");
        timestamp = Date.now();
    }
    return timestamp/1000;//JavaScript gives timestamps in milliseconds, but PHP expects them in seconds.
}

//This function checks the name in nameField against names of existing views search has found.
//Specifically, it disables makeViewButton to keep the user from making a view
//if the user has not first searched for existing nodes,
//if the name field is blank, or
//if the view name the user entered is an exact match for an existing one.
//Call it when we get new results for existing views or the user types in the nameField.
function checkNameAllowed() {
    var name = document.getElementById('nameField').value;
    document.getElementById('makeViewButton').disabled = !hasSearchedOnce || sources.hasOwnProperty(name) || (name.length < 1);
}//end of function checkNameAllowed

//recursively searches for children of a results row and fills in the appropriate information
function fillInResultsRow(element, viewName, viewPath, firstEditDatetime, lastEditDatetime) {
    element.viewName = viewName;//Just attach the view name to everything for easy reference later.
    //Check that it is actually a DOM element.
    if( element.nodeType == 1 ) {
        var elClass = element.getAttribute('class');
        switch( elClass ) {
            case 'result-link':
                element.textContent = viewName;
                element.setAttribute('href',viewPath);
            break;
            case 'first-time':
                element.textContent = firstEditDatetime;
            break;
            case 'last-time':
                element.textContent = lastEditDatetime;
            break;
            case 'delete-button':
                element.disabled = false;
            break;
            case 'use-checkbox':
                element.disabled = false;
            break;
            case 'up-to-datetime':
                element.disabled = false;
                element.min = firstEditDatetime;
                element.value = lastEditDatetime;
                element.max = lastEditDatetime;
            break;
            //Other elements besides the ones we modify may also be part of the template.
            //Just skip over these without comment.
            //default:
                //console.log("fillInResultsRow encountered unknown class");
                //console.log(elClass);
                //console.log("on element");
                //console.log(element);
        }//end of switch over possible classes
    }//end of if row or child of a row has class property
    for(var childIndex = 0; childIndex < element.childNodes.length; childIndex++) {
        fillInResultsRow(element.childNodes[childIndex], viewName, viewPath, firstEditDatetime, lastEditDatetime);
    }//end of loop through children of this element
}//end of function fillInResultsRow

function onResults() {
    //console.log("received response: "+this.responseText);
    try {
        var results = JSON.parse(this.responseText);
    }
    catch(exception) {
        console.log("could not parse response text: ");
        console.log(this.responseText);
        console.log(exception);
        return;
    }
    if(results.hasOwnProperty('error')) {
        console.log("error from server:");
        console.log(results);
        return;
    }
    else {
        console.log("received search results:");
        console.log(results);
    }
    sources = new Object();//Clear the previous possible sources.
    var resultsTable = document.getElementById('resultsTable');
    //Remove any results from previous searches.
    while(resultsTable.rows.length > 0) {
        resultsTable.removeChild(resultsTable.lastChild);
    }
    //For some reason, the JSON parsers expect the numeric arrays to come wrapped in Objects.
    results = results['results'];
    //If the search yielded no results, put back the "no results" item to notify the user.
    //The loop that follows will run through 0 iterations.
    if(results.length == 0) {
        resultsTable.appendChild(noResultsRow);
    }
    //Add a list item for each new result.
    var resultInfo = null;
    var resultRow = null;
    var name = null;
    var firstEditDatetime = null;
    var lastEditDatetime = null;
    for(var resultIndex = 0; resultIndex < results.length; resultIndex++) {
        resultRow = noResultsRow.cloneNode(true);//true->deep
        resultInfo = results[resultIndex];
        name = resultInfo['name'];
        firstEditDatetime = timestampToDatetimeLocalString(resultInfo['min_edit_timestamp']);
        lastEditDatetime = timestampToDatetimeLocalString(resultInfo['max_edit_timestamp']);
        sources[name] = new Object();
        sources[name][USE] = false;
        //This is what we will be sending to the server if we use the view as a source for a new view. It should be a timestamp:
        sources[name][CUTOFF] = resultInfo['max_edit_timestamp'];
        resultRow.id = name+"ResultRow";
        fillInResultsRow(resultRow, name, resultInfo['path'], firstEditDatetime, lastEditDatetime);
        resultsTable.appendChild(resultRow);
    }//end of loop through results
    hasSearchedOnce = true;
    checkNameAllowed();
}// end of function onResults

function search() {
    var searchRequest = new XMLHttpRequest();
    searchRequest.onload = onResults;
    var url = "../php/findview.php";
    var name = document.getElementById('nameField').value;
    if(name.length > 0) {
        url = url+"?name="+name;
    }
    searchRequest.open("get",url,true);
    searchRequest.send();
    console.log("sent request: "+url);
}//end of function search

function searchNoRefresh(event) {
    search();
    event.preventDefault();
    return false;
}//end of function searchNoRefresh

//Just use this as a callback whenever an event 
//could lead to a change in what views exist so
//that we should refresh the search results.
function onShouldRefreshSearch() {
    //console.log("received response: "+this.responseText);
    try {
        var results = JSON.parse(this.responseText);
    }
    catch(exception) {
        console.log("could not parse response text: ");
        console.log(this.responseText);
        console.log(exception);
        return;
    }
    if(results.hasOwnProperty('error')) {
        console.log("error from server:");
        console.log(results);
        return;
    }
    else {
        console.log("new view created:");
        console.log(results);
    }
    //Rerun the search, which should now show a result for the newly created view or lack the result for the deleted view.
    search();
}//end of function onNewView

function updateViewsToUse(event) {
    //viewName is the name of the view to check, 
    //which we attach to this element in fillInResultsRow.
    var name = event.target.viewName;
    sources[name][USE] = event.target.checked;
    //console.log(event);
    console.log("set use "+name+" to "+event.target.checked);
}//end of function updateViewsToUse

function updateCutoffDatetimes(event) {
    //viewName is the name of the view to check, 
    //which we attach to this element in fillInResultsRow.
    var name = event.target.viewName;
    var cutoff = datetimeLocalStringToTimestamp(event.target.value);
    sources[name][CUTOFF] = cutoff;
    console.log("set cutoff for "+name+" to "+cutoff);
}//end of 

function makeView() {
    //For brevity, convert the mapping from
    // { name1: { use:true/false, cutoff:timestamp }, ... } for all returned results to
    // { name1: timestamp, ... } for just the results to be used.
    // We maintain the bulkier object locally, because it is easier to update when the user checks/unchecks a checkbox or changes a cutoff date field.
    console.log("sources:");
    console.log(sources);
    var compactSources = new Object();
    for(name in sources) {
        if( sources[name][USE] ) {
            compactSources[name] = sources[name][CUTOFF];
        }
    }
    console.log("compactSources:");
    console.log(compactSources);
    var JSONSources = JSON.stringify(compactSources);
    console.log("as string:");
    console.log( JSONSources );
    var uriSources = encodeURIComponent(JSONSources);
    console.log("URI encoded:");
    console.log(uriSources);
    var makeRequest = new XMLHttpRequest();
    makeRequest.onload = onShouldRefreshSearch;
    var url = "../php/makeview.php?name="+document.getElementById('nameField').value+"&sources="+encodeURIComponent( JSON.stringify(compactSources) );
    makeRequest.open("get",url,true);
    makeRequest.send();
    console.log("sent request: "+url);
}//end of function duplicate

function deleteView(event) {
    var deleteRequest = new XMLHttpRequest();
    deleteRequest.onload = onShouldRefreshSearch;
    //viewName is the name of the view to delete, 
    //which we attach to this element in fillInResultsRow.
    var url = "../php/deleteview.php?name="+event.target.viewName;
    deleteRequest.open("get",url,true);
    deleteRequest.send();
    console.log("sent request: "+url);
}//end of function deleteView(event)