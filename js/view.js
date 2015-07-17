//value to use for items in local storage that should be empty:
var EMPTY = '';//null gets converted to the String 'null'.
//the SVG namespace to use when accessing DOM by tag:
var NAMESPACE = 'http://www.w3.org/2000/svg';
//key at which to locally store the name of the view that last called launchFindNode, 
//also ID of the title element holding the view ID in the SVG file:
var VIEW_ID = 'viewId';
//key at which to locally store the text of the node to modify:
var NODE_ID = 'nodeId';
//key at which to locally store the text of the node to modify:
var NODE_TEXT = 'nodeText';
//key at which to locally store the user name:
var USER_NAME = 'userName';
//ID of the element that contains both the node container and the edge container
var GRAPH_CONTAINER = 'graphContainer';
//ID of the element to which we add and from which we remove nodes:
var NODE_CONTAINER = 'nodeContainer';
//ID of element to which we add and from which we remove edges:
var EDGE_CONTAINER = 'edgeContainer';
//roles of the nodes in an edge:
var TO = 'to';
var LABEL = 'label';
var FROM = 'from';
//the properties of an evt that we use as x and y:
//(Plain x,y exist in Chrome but not Firefox.)
var EVT_X = 'clientX';
var EVT_Y = 'clientY';
//edit object keys:
var EDIT = 'edit';
var ID = 'id';
var X = 'x';
var Y = 'y';
var TEXT = 'text';
var FROM_ID = 'fromId';
var LABEL_ID = 'labelId';
var TO_ID = 'toId';
//var USER_NAME = 'userName';-tag each edit with a user name.
//has VIEW_ID if the edit is SET_VIEW_ID
//values of EDIT:
var SET_VIEW_ID = 'setViewId';
var SET_COORDINATES = 'setCoordinates';
var SET_TEXT = 'setText';
var MAKE_NODE = 'makeNode';
var REMOVE_NODE = 'removeNode';
var MAKE_EDGE = 'makeEdge';
var REMOVE_EDGE = 'removeEdge';
var LOCK_NODE = 'lockNode';
var UNLOCK_NODE = 'unlockNode';

//---LOGIN---

function loadOldUserName() {
    document.getElementById('userName').value = localStorage.getItem(USER_NAME);
}// end of function loadOldNodeText

function storeNewUserName(evt) {
    localStorage.setItem(USER_NAME,evt.target.value);
}// end of function storeNewNodeText

//---EDITNODETEXT---

function loadOldNodeText() {
    document.getElementById('nodeText').value = localStorage.getItem(NODE_TEXT);
    //When we are done editing, set the ID to null.
    //This signals the SVG view to unlock the node.
    window.addEventListener("beforeunload", function(evt){ localStorage.setItem(NODE_ID, null); }, false);
}// end of function loadOldNodeText

function storeNewNodeText(evt) {
    localStorage.setItem(NODE_TEXT,evt.target.value);
}// end of function storeNewNodeText

//alternate versions for a window opened using openDialog, which Chrome does not support.

//function loadOldNodeText() {
//    document.getElementById('nodeText').value = window.arguments[0].getText();
//}// end of function loadOldNodeText

//function storeNewNodeText(evt) {
//    window.arguments[0].setText(evt.target.value);
//}// end of function storeNewNodeText

function closeWhenDone(event) {
    console.log(event);
    localStorage.setItem(NODE_ID, null);
    close();
}

//---VIEW---

var viewId = null;
var nodeToMove = null;
var fromNode = null;
var labelNode = null;
var syncWorker = null;
//Keep track of the node the local user most recently locked for editing.
//We use this to make sure we unlock it when done.
var nodeBeingEdited = null;

//Add properties nodeText, dragTab, edgeTab as shortcuts to the appropriate child nodes.
function makeNodeShortcuts(node) {
    var child = null;
    var childClass = null;
    for(var childIndex = 0; childIndex < node.childNodes.length; childIndex++) {
        child = node.childNodes[childIndex];
        //If it has get, it should also have set.
        if( child.nodeType == 1 ) {
            childClass = child.getAttributeNS(null,'class')
            switch(childClass) {
                case 'node-text':
                    node.nodeText = child
                break;
                case 'move-tab':
                    node.moveTab = child;
                break;
                case 'edge-tab':
                    node.edgeTab = child;
                break;
                case 'remove-tab':
                    node.removeTab = child;
                break;
                default:
                    console.log("node "+node.id+" has child of unexpected class "+childClass+".");
            }//end of identifying which child it is by class
        }//end of if child is a proper DOM element with properties
        //else {
        //    console.log("node "+node.id+" has non-node child:");
        //    console.log(child);
        //}//end of if child is something else
    }//end of loop through children
}// end of function makeNodeShortcuts

function setNodeCoordinates(x,y) {
    //So that we can do arithmetic, make sure these are numbers.
    x = parseInt(x);
    y = parseInt(y);
    this.x = x;
    this.y = y;
    //Move the text.
    this.nodeText.setAttributeNS(null,'x',x+10);
    this.nodeText.setAttributeNS(null,'y',y);
    //Move the move tab.
    this.moveTab.setAttributeNS(null,'cx',x);
    this.moveTab.setAttributeNS(null,'cy',y);
    //Move the edge tab.
    this.edgeTab.setAttributeNS(null,'cx',x);
    this.edgeTab.setAttributeNS(null,'cy',y+11);
    //Move the remove tab.
    this.removeTab.setAttributeNS(null,'cx',x);
    this.removeTab.setAttributeNS(null,'cy',y+22);
    //Move all the edges. Each one keeps track of which role this node plays and moves itself accordingly.
    for(var edgeIndex = 0; edgeIndex < this.edges.length; edgeIndex++) {
        this.edges[edgeIndex].setCoordinates();
    }//end of loop through edges
}// end of function setNodeLocation

function getNodeX() {
    return this.x;
}// end of function getNodeX

function getNodeY() {
    return this.y;
}// end of function getNodeY

function setNodeText(text) {
    this.nodeText.textContent = text;
}// end of function setNodeText

function getNodeText() {
    return this.nodeText.textContent;
}// end of function getNodeText

function setNodeLocked(boolVal, ownerName) {
    this.locked = boolVal;
    this.lockOwner = ownerName;
    if(  this.localCanEdit()  ) {
        this.setAttributeNS(null,'class','node');
    }
    else {
        this.setAttributeNS(null,'class','node-locked');
    }
}// end of function setNodeLocked

function getNodeLocked() {
    return this.locked;
}// end of function getNodeLocked

function getNodeLockOwner() {
    return this.lockOwner;
}// end of function getNodeLockOwner

function canEditNode(userName) {
    return !this.getLocked() || ( this.getLockOwner() == userName );
}// end of function canEditNode

function localCanEditNode() {
    return this.canEdit( localStorage.getItem(USER_NAME) );
}//end of localCanEditNode

function makeNode(newId,newX,newY) {
    template = document.getElementById('nodeTemplate');
    newNode = template.cloneNode(true);//deep
    newNode.setAttributeNS( null, 'id', newId );
    makeNodeShortcuts(newNode);
    newNode.setCoordinates = setNodeCoordinates;
    newNode.setText = setNodeText;
    newNode.setLocked = setNodeLocked;
    newNode.getText = getNodeText;
    newNode.getX = getNodeX;
    newNode.getY = getNodeY;
    newNode.getLocked = getNodeLocked;
    newNode.getLockOwner = getNodeLockOwner;
    newNode.canEdit = canEditNode;
    newNode.localCanEdit = localCanEditNode;
    newNode.edges = [];
    document.getElementById(NODE_CONTAINER).appendChild(newNode);
    newNode.setCoordinates(newX,newY);
    newNode.setLocked(false, null);
}// end of makeNode

function removeNode(node) {
    document.getElementById(NODE_CONTAINER).removeChild(node);
    //When removing a node, remove all the edges that are part of it.
    for(var edgeIndex = 0; edgeIndex < node.edges.length; edgeIndex++) {
        removeEdge( node.edges[edgeIndex] );
    }//end of loop through edges
    //If it is any of the parts of the edge being constructed, reset all the edge parts to null.
    if( (fromNode == node)||(labelNode == node) ) {
        fromNode = null;
        labelNode = null;
    }//end of if it was one of the nodes in the edge under construction
}// end of function removeNode

//sets them based on the edge's current node components
function edgeSetCoordinates() {
    var from = this.nodes[FROM];
    var label = this.nodes[LABEL];
    var to = this.nodes[TO];
    //x,y for a node are stored as ints. See nodeSetCoordinates.
    var fromX = from.getX();
    var fromY = from.getY();
    var labelX = label.getX();
    var labelY = label.getY();
    var toX = parseInt( to.getX() );
    var toY = parseInt( to.getY() );
    var middleX = 2*labelX - (fromX + toX)/2;
    var middleY = 2*labelY - (fromY + toY)/2;
    var headAngleBase = Math.atan2( (toY - middleY), (toX - middleX) );
    var headAngle1 = headAngleBase + Math.PI/6;
    var headAngle2 = headAngleBase - Math.PI/6;
    var headX1 = toX - 30*Math.cos(headAngle1);
    var headY1 = toY - 30*Math.sin(headAngle1);
    var headX2 = toX - 30*Math.cos(headAngle2);
    var headY2 = toY - 30*Math.sin(headAngle2);
    var d = 'M '+fromX+' '+fromY+' Q '+middleX+' '+middleY+' '+toX+' '+toY+' L '+headX1+' '+headY1+' M '+toX+' '+toY+' L '+headX2+' '+headY2;
    this.setAttributeNS(null,'d',d);
}// end of function edgeSetCoordinates

function makeEdge(newId, newFrom, newLabel, newTo) {
    template = document.getElementById('edgeTemplate');
    newEdge = template.cloneNode(true);//deep
    newEdge.setAttributeNS( null,'id',newId );
    newEdge.nodes = new Object();
    newEdge.nodes[FROM] = newFrom;
    newEdge.nodes[LABEL] = newLabel;
    newEdge.nodes[TO] = newTo;
    newEdge.setCoordinates = edgeSetCoordinates;
    newEdge.setCoordinates();
    newFrom.edges.push(newEdge);
    newLabel.edges.push(newEdge);
    newTo.edges.push(newEdge);
    document.getElementById(EDGE_CONTAINER).appendChild(newEdge);
}// end of function makeEdge

function removeEdge(edge) {
    document.getElementById(EDGE_CONTAINER).removeChild(edge);
    var from = edge.nodes[FROM];
    var label = edge.nodes[LABEL];
    var to = edge.nodes[TO];
    var oldId = edge.id;
    //Delete it from each of the edge lists.
    var index = from.edges.indexOf(edge);
    from.edges.splice(index,1);
    index = label.edges.indexOf(edge);
    label.edges.splice(index,1);
    index = to.edges.indexOf(edge);
    to.edges.splice(index,1);
}// end of function removeEdge

function handleUpdateFromServer(edit) {
    console.log("received edit:");
    console.log(edit);
    switch(edit[EDIT]) {
        case SET_COORDINATES:
            var node = document.getElementById(edit[ID]);
            if(node != null) {
                if( node.canEdit(edit[USER_NAME]) ) {
                    node.setCoordinates(edit[X],edit[Y]);
                }
            }
        break;
        case SET_TEXT:
            var node = document.getElementById(edit[ID]);
            if(node != null) {
                if( node.canEdit(edit[USER_NAME]) ) {
                    node.setText(edit[TEXT]);
                }
            }
        break;
        case MAKE_NODE:
            makeNode(edit[ID],edit[X],edit[Y]);
        break;
        case REMOVE_NODE:
            var node = document.getElementById(edit[ID]);
            if(node != null) {
                if( node.canEdit(edit[USER_NAME]) ) {
                    removeNode(node);
                }
            }
        break;
        case LOCK_NODE:
            var node = document.getElementById(edit[ID]);
            if(node != null) {
                node.setLocked(true,edit[USER_NAME]);
            }
        break;
        case UNLOCK_NODE:
            var node = document.getElementById(edit[ID]);
            if(node != null) {
                //Check that the right user is doing the unlocking.
                if( node.getLockOwner() == edit[USER_NAME] ) {
                    node.setLocked(false,null);
                }
            }
        break;
        case MAKE_EDGE:
            var id = edit[ID];
            var from = document.getElementById(edit[FROM_ID]);
            var label = document.getElementById(edit[LABEL_ID]);
            var to = document.getElementById(edit[TO_ID]);
            if( (from != null) && (label != null) && (to != null) ) {
                makeEdge(id,from,label,to);
            }
        break;
        case REMOVE_EDGE:
            var edge = document.getElementById(edit[ID]);
            if(edge != null) {
                removeEdge(edge);
            }
        break;
        default:
            console.log("received unidentified edit command: "+edit[EDIT]);
    }//end of switch over different edits
}// end of syncWorkerOnMessage

//function syncWorkerOnMessage(event) {
//    handleUpdateFromServer(event.data);
//}// end of syncWorkerOnMessage

function postEdit(edit) {
    edit[USER_NAME] = localStorage.getItem(USER_NAME);
    //if( syncWorker != null ) {
    //    syncWorker.postMessage( edit );
    //    console.log("sent edit:");
    //    console.log(edit);
    //}
    sendEditToServer(edit);
}//end of function postEdit

//Set view ID just adds the view ID for future reference.
//No edit with this name comes back to the main thread.
function postSetViewId() {
    var edit = new Object();
    edit[EDIT] = SET_VIEW_ID;
    edit[VIEW_ID] = viewId;
    postEdit(edit);
}//end of function postSetViewId

function postSetCoordinates(node,x,y) {
    if( node.localCanEdit() ) {
        var edit = new Object();
        edit[EDIT] = SET_COORDINATES;
        edit[ID] = node.id;
        edit[X] = x;
        edit[Y] = y;
        postEdit(edit);
    }//end of if localCanEdit
}//end of function postSetCoordinates

function postSetText(node,text) {
    //We already check whether local can edit before we open the editor window
    //and check whether the editing user has the lock before making the change to the SVG image.
    //We do not need to look up the node by its ID here.
    //if( node.localCanEdit() ) {
    var edit = new Object();
    edit[EDIT] = SET_TEXT;
    edit[ID] = node.id;
    edit[TEXT] = text;
    postEdit(edit);
    //}//end of if localCanEdit
}//end of function postSetText

function postMakeNode(id,x,y) {
    var edit = new Object();
    edit[EDIT] = MAKE_NODE;
    edit[ID] = id;
    edit[X] = x;
    edit[Y] = y;
    postEdit(edit);
}//end of function postMakeNode

function postRemoveNode(node) {
    if( node.localCanEdit() ) {
        var edit = new Object();
        edit[EDIT] = REMOVE_NODE;
        edit[ID] = node.id;
        postEdit(edit);
    }//end of if localCanEdit
}//end of function postRemoveNode

function postLockNode(node) {
    if( node.localCanEdit() ) {
        var edit = new Object();
        edit[EDIT] = LOCK_NODE;
        edit[ID] = node.id;
        postEdit(edit);
    }//end of if localCanEdit
}//end of function postLockNode

function postUnlockNode(node) {
    if( node.localCanEdit() ) {
        var edit = new Object();
        edit[EDIT] = UNLOCK_NODE;
        edit[ID] = node.id;
        postEdit(edit);
    }//end of if localCanEdit
}//end of function postUnlockNode

function postMakeEdge(id,from,label,to) {
    var edit = new Object();
    edit[EDIT] = MAKE_EDGE;
    edit[ID] = id;
    edit[FROM_ID] = from.id;
    edit[LABEL_ID] = label.id;
    edit[TO_ID] = to.id;
    postEdit(edit);
}//end of function postMakeEdge

function postRemoveEdge(edge) {
    var edit = new Object();
    edit[EDIT] = REMOVE_EDGE;
    edit[ID] = edge.id;
    postEdit(edit);
}//end of function postRemoveEdge

function viewOnload() {
    viewId = document.getElementById(VIEW_ID).textContent;
    addEventListener('storage',nodeOnLocalStorageChange);
    //if( window.Worker ) {
    //    syncWorker = new Worker('../js/syncworker.js');
    //    syncWorker.onmessage = syncWorkerOnMessage;
    //}
    //if(syncWorker == null) {
    //    console.log("failed to create sync worker");
    //}
    postSetViewId();
    //console.log("view loaded");
}// end of function onloadView

function launchEditNodeText(evt) {
    var node = evt.target.parentNode;
    if( node.localCanEdit() ) {
        localStorage.setItem(VIEW_ID, viewId);
        localStorage.setItem(NODE_ID, node.id);
        //Sets from within the same script do not trigger an event.
        postLockOrUnlock(viewId, node.id);
        localStorage.setItem( NODE_TEXT, node.getText() );
        var strWindowFeatures = "left="+( screenX+node.getX() )+",top="+( screenY + node.getY() )+",width=300,height=100,menubar=0,toolbar=0,location=0,personalbar=0,status=0,dialog=1,scrollbars=0,titlebar=0,alwaysRaised=1";
        var editor = open('../html/editnodetext.html', node.id, strWindowFeatures);
        if( editor == null ) {
            //If window opening failed, unset the node ID so that we will unlock the node.
            postLockOrUnlock(viewId, null);
        }
    }//end of if localCanEdit
    stopBubbling(evt);
}// end of function launchEditNodeText

//alternate version for a window opened using openDialog, which Chrome does not support.

//function launchEditNodeText(evt) {
//    var node = evt.target.parentNode;
//    var strWindowFeatures = "left="+node.getX()+",top="+node.getY()+",width=300,height=100,all=no,alwaysRaised=yes";
//    //menubar=0,toolbar=0,location=0,personalbar=0,status=0,dialog=1,scrollbars=0,titlebar=0,alwaysRaised=1";
//    //var argObject = new Object();
//    //argObject[VIEW_ID] = viewId;
//    //argObject[NODE_ID] = node.id;
//    //argObject[NODE_TEXT] = node.getText();
//    window.openDialog('../html/editnodetext.html', node.id, strWindowFeatures, node);
//    stopBubbling(evt);
//}// end of function launchEditNodeText

function postLockOrUnlock(editViewId, editNodeId) {
    //Unlock any node that is currently locked for editing.
    if(nodeBeingEdited != null) {
        postUnlockNode(nodeBeingEdited);
        nodeBeingEdited = null;
    }//end of if some node is locked for editing
    if( (editViewId == viewId) && (editNodeId != null) ) {
        nodeBeingEdited = document.getElementById(editNodeId);
        if(nodeBeingEdited != null) {
            postLockNode(nodeBeingEdited);
        }
    }//end of we have a new node to edit in this view
}// end of function postLockOrUnlock

function nodeOnLocalStorageChange(evt) {
    console.log(evt);
    var editViewId = localStorage.getItem(VIEW_ID);
    var editNodeId = localStorage.getItem(NODE_ID);
    if(evt.key == NODE_TEXT) {
        if( (editViewId == viewId) && (nodeBeingEdited != null) ) {
            //When the user updates a node we have from find, set the node in the view to match.
            postSetText( nodeBeingEdited, localStorage.getItem(NODE_TEXT) );
        }// end of if the current document is the view being modified
    }//end of if the text changed
    else if( (evt.key == VIEW_ID) || (evt.key == NODE_ID) ) {
        postLockOrUnlock(editViewId, editNodeId);
    }//end of if the view or node being edited changed
}// end of function nodeOnLocalStorageChange

function stopBubbling(evt) {
    if (evt.stopPropagation) evt.stopPropagation();
    if (evt.cancelBubble != null) evt.cancelBubble = true;
}// end of function stopBubbling

function setNodeToMove(evt) {
    var node = evt.target.parentNode;
    nodeToMove = node;
    stopBubbling(evt);
}// end of function setNodeToMove

function backgroundOnclick(evt) {
    console.log(evt);
    if(nodeToMove != null) {
        postSetCoordinates(nodeToMove, evt[EVT_X], evt[EVT_Y]);
        nodeToMove = null;
    }
    else {
        postMakeNode( Math.random(), evt[EVT_X], evt[EVT_Y] );
    }
    stopBubbling(evt);
}// end of function backgroundOnclick

function onclickRemoveNode(evt) {
    postRemoveNode(evt.target.parentNode);
    stopBubbling(evt);
}// end of onclickRemoveNode

function onclickRemoveEdge(evt) {
    postRemoveEdge(evt.target);
    stopBubbling(evt);
}// end of function removeEdgeOnRightClick

function addToEdge(evt) {
    if(fromNode == null) {
        fromNode = evt.target.parentNode;
        console.log("From set.");
    }
    else if(labelNode == null) {
        labelNode = evt.target.parentNode;
        console.log("Label set.");
    }
    else {
        var toNode = evt.target.parentNode;
        console.log("To set.");
        postMakeEdge(Math.random(), fromNode, labelNode, toNode);
        fromNode = null;
        labelNode = null;
    }
    stopBubbling(evt);
}// end of function addToEdge