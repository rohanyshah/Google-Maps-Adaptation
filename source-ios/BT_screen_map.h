/*
 *	Copyright, Rohan Shah
 *
 *	All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without modification, are 
 *	permitted provided that the following conditions are met:
 *
 *	Redistributions of source code must retain the above copyright notice which includes the
 *	name(s) of the copyright holders. It must also retain this list of conditions and the 
 *	following disclaimer. 
 *
 *	Redistributions in binary form must reproduce the above copyright notice, this list 
 *	of conditions and the following disclaimer in the documentation and/or other materials 
 *	provided with the distribution. 
 *
 *	Neither the name of David Book, or buzztouch.com nor the names of its contributors 
 *	may be used to endorse or promote products derived from this software without specific 
 *	prior written permission.
 *
 *	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND 
 *	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED 
 *	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. 
 *	IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, 
 *	INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT 
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR 
 *	PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, 
 *	WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY 
 *	OF SUCH DAMAGE. 
 */


#import <Foundation/Foundation.h>
#import <UIKit/UIKit.h>
#import "BT_viewController.h"
#import "MapKit/MapKit.h"
#import "MapKit/MKAnnotation.h"
#import "BT_downloader.h"
#import "BT_item.h"

@interface BT_screen_map : BT_viewController <BT_downloadFileDelegate,
											MKMapViewDelegate,
											UIActionSheetDelegate>{
	NSMutableArray *mapLocations;
	MKMapView *mapView;
	UIToolbar *mapToolbar;
	int didInitialPinDrop;
	BT_item *driveToLocation;
	BT_downloader *downloader;
	NSString *saveAsFileName;	
	int didInitMap;	
}

@property (nonatomic, retain) NSMutableArray *mapLocations;
@property (nonatomic, retain) MKMapView *mapView;
@property (nonatomic, retain) UIToolbar *mapToolbar;
@property (nonatomic) int didInitialPinDrop;
@property (nonatomic) int didInitMap;

@property (nonatomic, retain) BT_item *driveToLocation;
@property (nonatomic, retain) NSString *saveAsFileName;
@property (nonatomic, retain) BT_downloader *downloader;


-(void)calloutButtonTapped:(id)sender;
-(void)centerMap;
-(void)centerDeviceLocation;
-(void)showMapType:(id)sender;
-(void)showDirectionsToLocation:(BT_item *)theLocation;

//map view delegate methods
-(void)mapView:(MKMapView *)mapView regionWillChangeAnimated:(BOOL)animated;
-(void)mapView:(MKMapView *)mapView regionDidChangeAnimated:(BOOL)animated;
-(MKAnnotationView *) mapView:(MKMapView *)mapView viewForAnnotation:(id)annotation;
-(void)mapView:(MKMapView *)mapView didAddAnnotationViews:(NSArray *)views;
-(void)mapViewWillStartLoadingMap:(MKMapView *)mapView;
-(void)mapViewDidFinishLoadingMap:(MKMapView *)mapView;
-(void)mapViewDidFailLoadingMap:(MKMapView *)mapView withError:(NSError *)error;

//actionsheet delegate methods
-(void)actionSheet:(UIActionSheet *)actionSheet  clickedButtonAtIndex:(NSInteger)buttonIndex;


-(void)loadData;
-(void)refreshData;
-(void)downloadData;
-(void)layoutScreen;
-(void)parseScreenData:(NSString *)theData;



@end
