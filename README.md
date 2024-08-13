# MultiPlayerMod
Backend for a multiplayer mod for construct 3 without req an dedicated server or WebSocket for communication with users.


With your budget in mind the hybrid approach of using AJAX for server-client communication and WebSockets for client-to-client communication is a good compromise. This can enable you have a stable and highly efficient multiplayer system, where real-time communications between players are handled by WebSockets while AJAX serves other purposes like user rewards.


This will reduce your server’s workload since it will be able to easily communicate with clients directly rather than sending requests on behalf of them after the introduction of web sockets.Why this may be an option includes cutting down on server resources and bandwidth consumption hence making it cheaper.







The code:

1. server.php: This script manages the start and end of sessions, and communicates with the client-side via WebSockets or AJAX.

2. auth.php: This script authenticates users and checks if they have entered valid information.

3. query.php: This script checks for new users and adds them to a waiting list for other players to join.

4. session.php: This script chooses players randomly 4 player and assigns a host with best ping amoung them and players for a game session (host is also the player);Useing two property sessionOn.php and sessionEnd.php.
