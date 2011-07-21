create table phonebook (
    id          INTEGER         PRIMARY KEY AUTO_INCREMENT,
    name   	varchar(80)     NOT NULL,
    number	varchar(80)	NOT NULL
);

create table rate (
    id          INTEGER         PRIMARY KEY AUTO_INCREMENT,
    pattern     varchar(80)     NOT NULL,
    amount        float(10,2)     NOT NULL
);

create table userpin(
    id          INTEGER     PRIMARY KEY AUTO_INCREMENT,
    pinset_id   INTEGER     NOT NULL,
    user_id     INTEGER     NOT NULL,
    pin         INTEGER     NOT NULL,
    active      INTEGER     NOT NULL,
    startDate   INTEGER     NOT NULL,
    endDate     INTEGER     
);

create table pinset(
    id        INTEGER PRIMARY KEY AUTO_INCREMENT,
    freepbxpinset_id INTEGER NOT NULL,
    description VARCHAR(80) NOT NULL,
    active    INTEGER NOT NULL,
    foundDate INTEGER NOT NULL,
    lostDate  INTEGER
);

create table ctuser(
	id 	INTEGER 	PRIMARY KEY AUTO_INCREMENT,
	acluser_id INTEGER	NOT NULL,
	username varchar(50)	NOT NULL,
        lname    varchar(50)    NOT NULL,
	active	INTEGER		NOT NULL,
	foundDate INTEGER	NOT NULL,
	lostDate  INTEGER	
);

create table report(
	id          INTEGER 	PRIMARY KEY AUTO_INCREMENT,
	dst         varchar(80),
	duration    INTEGER,
        total       INTEGER,
	accountcode varchar(80),
	src         varchar(80),
	calldate    datetime,
	username    varchar(80),
        cost        float(10,2)
);

create fulltext index pattern on report (dst);
create index callate on report (calldate);
create index dates on userpin (startDate,endDate);
