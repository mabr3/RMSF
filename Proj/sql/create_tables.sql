USE ist176176;







CREATE TABLE Player(

	id int  NOT NULL AUTO_INCREMENT,

	reference varchar(20) NULL,

	kills int NULL,

	deaths int NULL,

	lat varchar(15) NULL,

	lon varchar(15) NULL,

	lastUpdateTime DateTime NOT NULL,

	PRIMARY KEY (id)

);





CREATE TABLE Mine(

	reference varchar(20) NULL,

	lat varchar(15) NULL,

	lon varchar(15) NULL,

	mineTime DateTime NOT NULL



);





CREATE TABLE DeathLog(

	referenceKiller varchar(20) NULL,

	referenceKilled varchar(20) NULL,

	method varchar(10) NOT NULL,

	lat varchar(15) NULL,

	lon varchar(15) NULL,

	deathTime DateTime NOT NULL



);
