# MultiPlayerMod
Backend for a multiplayer mod for construct 3 without req an dedicated server or WebSocket for communication with users.


The hybrid approach of using WebSockets for client-to-client communication and AJAX for server-client communication is a good compromise, given your budget constraints.
This approach can help you achieve a robust and efficient multiplayer system, with WebSockets handling real-time communication between players and AJAX handling server-side tasks like rewarding users.





By using WebSockets for client-to-client communication, you can reduce the load on your server and minimize the need for frequent server-side requests.
This approach can help you save on server resources and bandwidth, making it a cost-effective solution.








The code:

1. server.php: This script manages the start and end of sessions, and communicates with the client-side via WebSockets or AJAX.

2. auth.php: This script authenticates users and checks if they have entered valid information.

3. query.php: This script checks for new users and adds them to a waiting list for other players to join.

4. session.php: This script chooses players randomly 4 player and assigns a host with best ping amoung them and players for a game session (host is also the player);Useing two property sessionOn.php and sessionEnd.php.
