# tprp - Turning Point Radio Podcast
A service for parsing David Jeremiah's Turning Point radio program and outputting it as a podcast.

This is a package designed for deployment to Google Cloud Compute App Engine.

~~It is currently deployed to: http://turning-point-radio-podcast.appspot.com~~
I took it down to free up capacity in my GCP account.

## HTTP GET commands
  ### limit=24
  Number of episodes to serve. Defaults to 24.
  
## HTTP GET commands FOR ADMIN USE ONLY – PLEASE DO NOT USE THESE COMMANDS ON http://turning-point-radio-podcast.appspot.com
  ### refetchMediaURLs=0
  Force fetching each radio page and scraping it for the mediaURL, for every episode starting at the current one minus *refetchMediaURLs*, going back to the current one minus *limit*. If *refetchMediaURLs* is greater than *limit*, no mediaURLs will be refetched.
  
  ### fetchAllOverride
  Force fetching the episode list and parsing the whole thing. Default behavior is to parse only episodes newer than the last one in the database.
  
