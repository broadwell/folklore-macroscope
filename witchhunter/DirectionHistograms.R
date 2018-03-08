library(ggplot2)

rotate <- function(x) t(apply(x, 2, rev))
cbbPalette <- c("#000000", "#E69F00", "#56B4E9", "#009E73", "#F0E442", "#0072B2", "#D55E00", "#CC79A7")

args <- commandArgs(TRUE)
abbrev <- args[1]

setwd("direction_files/")
# Load the raw distance numbers (no frequencies, just repetition)
Dvec = scan(paste0(abbrev, "_distances.dat"))
setwd("../direction_graphs/")
png(paste0(abbrev, "_distances.png"), width=500, height=500)
hist(Dvec, breaks=seq(0,500,by=5), main=paste0(abbrev, " place mentioned distances, bin size: 5km"), col="red", xlim=c(0,300), xlab="Distance (km)", ylab="Places mentioned in stories at this distance")

# ylim=c(0,500))

setwd("../direction_files/")
# Load the distance histogram derivatives
Mat <- as.matrix(read.table(paste0(abbrev, "_normdist.dat"), header = FALSE, nrows = 2))
Mrot <- rotate(Mat)
Mdat <- as.data.frame(Mrot)
colnames(Mdat) <- c("freqs", "distance")
setwd("../direction_graphs/")
# Use to plot a histogram of bearings; bars will be longer if the have > freq
# polar histogram with binsize=30 (computed manually via PHP)
png(paste0(abbrev, "_normdist.png"), width=500, height=500)
#ggplot(Mdat, aes(x=distance, y=freqs)) + geom_histogram(stat="identity", width=500, binwidth=5, drop=TRUE, colour="red", fill="white") + scale_x_continuous(breaks=seq(0,500,by=5)) + ggtitle(paste0(abbrev, " normalized place mentioned distances")) + xlab("Distance") + ylab=("Normalized places mentioned at this distance")
qplot(distance, freqs, data=Mdat, geom="line", main=paste0(abbrev, " normalized place mentioned distances"), xlab="Distance (km), bin size: 1km", ylab="Normalized places mentioned at this distance", xlim=c(0,300))

setwd("../direction_files/")
# Load the binned direction histograms (with counts for 30, 60, 90 degrees...) 
Mat <- as.matrix(read.table(paste0(abbrev, "_binhist.dat"), header = FALSE, nrows = 2))
Mrot <- rotate(Mat)
Mdat <- as.data.frame(Mrot)
colnames(Mdat) <- c("freqs", "degrees")
setwd("../direction_graphs/")
# Use to plot a histogram of bearings; bars will be longer if the have > freq
# polar histogram with binsize=30 (computed manually via PHP)
png(paste0(abbrev, "_binhist.png"), width=500, height=500)
ggplot(Mdat, aes(x=degrees, y=freqs)) + geom_histogram(stat="identity", width=30, binwidth=30, drop=TRUE, colour="red", fill="white") + coord_polar(theta="x") + scale_x_continuous(breaks=seq(0, 330, by=30)) + ggtitle(paste0(abbrev, " place mentioned bearings, bin size: 30 degrees")) + xlab("Degrees") + ylab("Places mentioned in this direction") 

setwd("../direction_files/")
# Load the full (one per degree) direction histogram
Mat <- as.matrix(read.table(paste0(abbrev, "_hist.dat"), header = FALSE, nrows = 2))
Mrot <- rotate(Mat)
Mdat <- as.data.frame(Mrot)
colnames(Mdat) <- c("freqs", "degrees")
setwd("../direction_graphs/")
png(paste0(abbrev, "_bearings.png"), width=500, height=500)
# Use to plot a histogram of bearings; bars will be longer if the have > freq
ggplot(Mdat, aes(x = degrees, y = freqs)) + geom_histogram(stat="identity", width=1, colour = "red", fill = "white") + ggtitle(paste0(abbrev, " place mentioned bearings, bin size: 1 degree")) + scale_x_continuous(breaks=seq(0, 330, by=30)) + coord_polar(theta="x") + xlab("Degrees") + ylab("Places mentioned in this direction")

setwd("../direction_files/")
# Needed for point plot with scaled points
Mat <- as.matrix(read.table(paste0(abbrev, "_polarcoords.dat"), header = FALSE, nrows = 3))
Mrot <- rotate(Mat)
Mdat <- as.data.frame(Mrot)
colnames(Mdat) <- c("places", "distance", "degrees")
setwd("../direction_graphs/")
png(paste0(abbrev, "_logplot.png"), width=500, height=500)
# plot actual distances and bearings as points; log scaled to see nearby places
ggplot(Mdat, aes(x = degrees, y = distance, size=places, color=places)) + ggtitle(paste0(abbrev, " relative places mentioned")) + scale_x_continuous(breaks=seq(0, 330, by=30)) + scale_y_log10() + geom_point() + coord_polar(theta="x") + scale_colour_gradientn(colours=rainbow(4)) + xlab("Degrees") + ylab("Distance (km), log10 scale")

#png(paste0(abbrev, "_interp.png"), width=500, height=500)
# plots points and spatially interpolated kde regions a log scale
# old interpolation code; doesn't take into account frequencies
#ggplot(Mdat, aes(x = degrees, y = distance, size=freqs, color=freqs)) + ggtitle(paste0(abbrev, " kde log plot")) + scale_x_continuous(breaks=seq(0, 330, by=30)) + scale_y_log10() + coord_polar(theta="x") + stat_density2d(aes(fill=..level..), geom="polygon", alpha=0.7) + scale_fill_gradient(low="yellow", high="darkred") + geom_point(alpha=0.5)

setwd("../direction_files/")
# Needed for alternate heatmap
Mat <- as.matrix(read.table(paste0(abbrev, "_polarfreqs.dat"), header = FALSE, nrows = 3))
Mrot <- rotate(Mat)
Mdat <- as.data.frame(Mrot)
colnames(Mdat) <- c("freqs", "distance", "degrees")
setwd("../direction_graphs/")
png(paste0(abbrev, "_freq.png"), width=500, height=500)
# plot actual distances and bearings as points; log scaled to see nearby places
ggplot(Mdat, aes(x = degrees, y = distance)) + ggtitle(paste0(abbrev, " relative places mentioned heat map")) + scale_x_continuous(breaks=seq(0, 330, by=30)) + scale_y_log10() + coord_polar(theta="x") + stat_density2d(aes(fill=..level..), geom="polygon", alpha=0.7) + scale_fill_gradient(low="yellow", high="darkred") + geom_point(alpha=0.3) + xlab("Degrees") + ylab("Distance (km), log10 scale") + labs(fill='avg places')

setwd("../direction_files/")
# Needed for alternate heatmap using Cartesian points
Mat <- as.matrix(read.table(paste0(abbrev, "_cartcoords.dat"), header = FALSE, nrows = 3))
Mrot <- rotate(Mat)
Mdat <- as.data.frame(Mrot)
colnames(Mdat) <- c("freqs", "Y", "X")
setwd("../direction_graphs/")
png(paste0(abbrev, "_cart.png"), width=500, height=500)

#ggplot(Mdat, aes(x = X, y = Y)) + coord_cartesian(xlim=c(-300,300),ylim=c(-300,300)) + stat_density2d(aes(fill=..level..), geom="polygon", alpha=0.7) + scale_fill_gradient(low="yellow", high="darkred") + geom_point(alpha=0.3)
ggplot(Mdat, aes(x = X, y = Y)) + ggtitle(paste0(abbrev, " relative places mentioned heat map 2")) + coord_cartesian(xlim=c(-3,3),ylim=c(-3,3)) + stat_density2d(aes(fill=..level..), geom="polygon", alpha=0.7) + scale_fill_gradient(low="yellow", high="darkred") + geom_point(alpha=0.3) + xlab("East-west distance (km), log10 scale") + ylab("North-south distance (km), log10 scale") + scale_x_discrete(breaks = -2:2, labels=c("100","10","0","10","100")) + scale_y_discrete(breaks = -2:2, labels=c("100","10","0","10","100")) + theme(aspect.ratio=1) + labs(fill='avg places')

setwd("../direction_files/")
# Needed for alternate heatmap using Cartesian points (without 0,0 blocked out)
Mat <- as.matrix(read.table(paste0(abbrev, "_cartcoords2.dat"), header = FALSE, nrows = 3))
Mrot <- rotate(Mat)
Mdat <- as.data.frame(Mrot)
colnames(Mdat) <- c("freqs", "Y", "X")
setwd("../direction_graphs/")
png(paste0(abbrev, "_cart2.png"), width=500, height=500)

#ggplot(Mdat, aes(x = X, y = Y)) + coord_cartesian(xlim=c(-300,300),ylim=c(-300,300)) + stat_density2d(aes(fill=..level..), geom="polygon", alpha=0.7) + scale_fill_gradient(low="yellow", high="darkred") + geom_point(alpha=0.3)
ggplot(Mdat, aes(x = X, y = Y)) + ggtitle(paste0(abbrev, " relative places mentioned heat map 3")) + coord_cartesian(xlim=c(-3,3),ylim=c(-3,3)) + stat_density2d(aes(fill=..level..), geom="polygon", alpha=0.7) + scale_fill_gradient(low="yellow", high="darkred") + geom_point(alpha=0.3) + xlab("East-west distance (km), log10 scale") + ylab("North-south distance (km), log10 scale") + scale_x_discrete(breaks = -2:2, labels=c("100","10","0","10","100")) + scale_y_discrete(breaks = -2:2, labels=c("100","10","0","10","100")) + theme(aspect.ratio=1) + labs(fill='avg places')

dev.off()

# plots spatially interpolated points on a linear scale
#ggplot(Mdat, aes(x = degrees, y = distance, size=freqs, color=freqs)) + ggtitle(paste0(abbrev, " kde log plot")) + scale_x_continuous(breaks=seq(0, 330, by=30)) + coord_polar(theta="x") + stat_density2d(aes(fill=..level..), geom="polygon", alpha=0.7) + scale_fill_gradient(low="yellow", high="darkred") + geom_point(alpha=0.5)

# More sophisticated polar plotting code
#res <- 1 # 1 degree resolution
#x_cell_lim <- c(180, -180) + c(1, -1) * res/2
#y_cell_lim <- c(90, 60) + c(1, -1) * res/2
 
# ggplot(aes(x = x, y = y, fill = value), data = dat_grid) + geom_tile() + 
#   geom_path(data = ant_ggplot) + 
#     scale_fill_gradient(low = "white", high = muted("red")) + 
#       coord_polar(start = -pi/2, expand = FALSE) +
#         scale_y_continuous("") + scale_x_continuous("") + 
#           xlim(x_cell_lim) + ylim(y_cell_lim) +
#             opts(axis.ticks = theme_blank(), axis.text.y = theme_blank(), 
#                    axis.title.x = theme_blank(), axis.title.y = theme_blank(),
#                           panel.border = theme_blank())
