# How tweets are collected?

 - Decide whether collect should be skipped when
    - the member is protected,
    - the member has been declared as not found,
    - the member is suspended,
    - the publishers list has been locked or
    - the member is a whisperer
    (a member who has not published anything for quite some time).

 - Update the extremum option by considering
    - the discovery of tweets with id lesser than max id
    (max id option is removed otherwise)
    - finding the next extremum (when max id is defined)
    or a local maximum:    
    in order to find a local maximum (local with regard to a member),
    the descending order of ids is applied, so that the biggest
    publication id is identified.
    Member profiles are also updated with their extremum. 
    
 - Fetch tweets.
 
 - Update member profile with collected extremum.
 
 - Compare most recent publication id with boundaries
 before looking for tweets in the opposite direction
 (by using a lower bound) and interrupt collection 
 when there is no reason to look further. 
 
 - Save member tweets.
 
 - Identify whisperers (members for whom, no publication has been collected)
 
 - Try collecting further by discovering tweets
 with id greater than since id
